<?php
namespace Tests\Feature;

use App\Models\{Business, BusinessCategory, BusinessCategoryRentalRate, Market, MarketSection, RentalAssessment, RentalBill, RentalPolicy, Stall, Tenant, User};
use App\Services\{AssignmentService, BillingService, RentalCalculationService, RentalOverrideService};
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FixedDayBillingTest extends TestCase
{
    use RefreshDatabase;
    private $actor;
    private $tenant;
    private $business;
    private $stall;

    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.fixed_day_enabled' => true, 'billing.default_interval_days' => 7]);
        Carbon::setTestNow('2026-11-25 12:00:00');
        $this->seed(RoleSeeder::class);
        $this->actor = User::create(['name'=>'Fictional reviewer','email'=>'fixed@example.test','password'=>'unused','active'=>true]);
        $this->actor->assignRole('Super Administrator');
        $market = Market::create(['code'=>'FIX','name'=>'Fictional weekly market','status'=>'active']);
        $section = MarketSection::create(['market_id'=>$market->id,'code'=>'A','name'=>'Fictional section','status'=>'active']);
        $this->stall = Stall::create(['market_id'=>$market->id,'market_section_id'=>$section->id,'code'=>'FIX-1',
            'classification'=>'standard','location_type'=>'inner','area'=>'10.00','status'=>'vacant']);
        $this->tenant = Tenant::create(['reference'=>'FIX-T','name'=>'Fictional tenant','status'=>'active']);
        $category = BusinessCategory::create(['code'=>'FIX','name'=>'Fictional weekly trade','is_active'=>true]);
        BusinessCategoryRentalRate::create(['business_category_id'=>$category->id,'minimum_rate'=>'20.00','maximum_rate'=>'25.00',
            'status'=>'approved','effective_from'=>'2020-01-01','approved_by'=>$this->actor->id,'approved_at'=>now(),
            'created_by'=>$this->actor->id,'reference_document'=>'SYNTHETIC ONLY']);
        $this->business = Business::create(['tenant_id'=>$this->tenant->id,'business_category_id'=>$category->id,
            'name'=>'Fictional trade','permitted_goods'=>'Fictional goods','status'=>'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function assign(array $terms = [])
    {
        return app(AssignmentService::class)->assign($this->actor, $terms + [
            'tenant_id'=>$this->tenant->id,'stall_id'=>$this->stall->id,'business_id'=>$this->business->id,
            'start_date'=>'2026-11-01','agreement_reference'=>'FIX-AGREEMENT','daily_cusa'=>'10.00',
        ]);
    }

    public function test_weekly_assignment_needs_no_policy_and_records_cusa_without_charging_it()
    {
        $assignment = $this->assign();
        $assessment = $assignment->agreement->assessment;
        $this->assertTrue($assessment->isFixedDays());
        $this->assertNull($assessment->rental_policy_id);
        $this->assertSame(7, $assessment->billing_days);
        $this->assertSame('2026-11-01', $assessment->period);
        $this->assertSame('200.00', $assessment->daily_rental);
        $this->assertSame('10.00', $assessment->daily_cusa);
        $this->assertSame('70.00', $assessment->monthly_cusa);
        $this->assertSame('1400.00', $assessment->total_monthly_rental_cusa);
        $this->assertSame('0.00', $assessment->total_initial_payment);
        $this->assertFalse($assessment->basis['cusa_chargeable']);
        $this->assertDatabaseCount('rental_policies', 0);
        $this->assertDatabaseCount('rental_bills', 0);
    }

    public function test_two_weeks_in_one_month_are_separate_idempotent_bills_with_frozen_terms()
    {
        $assignment = $this->assign();
        $initial = $assignment->agreement->assessment->getRawOriginal();
        $service = app(BillingService::class);
        $first = $service->generate($this->actor, $assignment, '2026-11-01', '2026-11-07');
        config(['billing.default_interval_days'=>14, 'billing.fixed_day_enabled'=>false]);
        $this->stall->update(['area'=>'50.00']);
        $second = $service->generate($this->actor, $assignment, '2026-11-08', '2026-11-14');
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('1400.00', $second->amount);
        $this->assertSame('2026-11-08', $second->coverage_start);
        $this->assertSame('2026-11-14', $second->coverage_end);
        $this->assertSame('2026-11-08 – 2026-11-14', $second->periodLabel());
        $this->assertEquals(['1400.00'], $second->items()->pluck('amount')->all());
        $this->assertSame($first->id, $service->generate($this->actor, $assignment, '2026-11-01', '2026-12-31')->id);
        $this->assertSame('2026-11-07', $first->fresh()->due_date);
        $this->assertSame($initial, $assignment->agreement->assessment->fresh()->getRawOriginal());
        $service->post($this->actor, $first);
        $this->assertSame('1400.00', $first->fresh()->balance());
    }

    public function test_custom_interval_crosses_month_and_leap_boundaries_using_exact_money()
    {
        $assignment = $this->assign(['start_date'=>'2024-02-26','billing_interval_days'=>5,'daily_cusa'=>'1.23']);
        $bill = app(BillingService::class)->generate($this->actor, $assignment, '2024-02-26', '2024-03-01');
        $this->assertSame('2024-03-01', $bill->coverage_end);
        $this->assertSame('1000.00', $bill->amount);
        $this->assertSame('6.15', $bill->assessment->monthly_cusa);
        $next = app(BillingService::class)->generate($this->actor, $assignment, '2024-03-02', '2024-03-06');
        $this->assertSame('1000.00', $next->amount);
    }

    /** @dataProvider invalidPeriods */
    public function test_rejects_wrong_mode_misalignment_future_and_incomplete_intervals($period, $end)
    {
        $assignment = $this->assign(['end_date'=>$end]);
        $this->expectException(ValidationException::class);
        try {
            app(BillingService::class)->generate($this->actor, $assignment, $period, '2026-12-31');
        } finally {
            $this->assertDatabaseCount('rental_bills', 0);
            $this->assertDatabaseCount('rental_assessments', 1);
        }
    }

    public function invalidPeriods()
    {
        return [['2026-11',null],['2026-11-02',null],['2026-10-25',null],['2026-11-29',null],['2026-11-08','2026-11-10']];
    }

    public function test_saved_coverage_prevents_end_or_transfer_cutting_a_bill()
    {
        $assignment = $this->assign();
        app(BillingService::class)->generate($this->actor, $assignment, '2026-11-08', '2026-11-14');
        $this->expectException(ValidationException::class);
        app(AssignmentService::class)->end($this->actor, $assignment, '2026-11-10', 'Fictional early closure');
    }

    public function test_old_monthly_assignment_retains_cusa_and_monthly_bills_after_opt_in()
    {
        config(['billing.fixed_day_enabled'=>false]);
        RentalPolicy::create(['market_id'=>$this->stall->market_id,'daily_cusa'=>'10.00','billing_day_rule'=>'fixed',
            'fixed_billing_days'=>30,'max_concurrent_assignments'=>1,'advance_deposit_authorized'=>true,
            'status'=>'approved','effective_from'=>'2020-01-01','approved_by'=>$this->actor->id,'approved_at'=>now(),
            'created_by'=>$this->actor->id,'reference_document'=>'SYNTHETIC ONLY']);
        $assignment = $this->assign();
        config(['billing.fixed_day_enabled'=>true]);
        $bill = app(BillingService::class)->generate($this->actor, $assignment, '2026-11', '2026-11-30');
        $this->assertFalse($bill->assessment->isFixedDays());
        $this->assertSame('6300.00', $bill->amount);
        $this->assertEquals(['6000.00','300.00'], $bill->items()->pluck('amount')->all());
        $this->assertNull($bill->coverage_start);
    }

    public function test_fixed_day_assignment_cannot_enter_monthly_override_workflow()
    {
        $assignment = $this->assign();
        $this->expectException(ValidationException::class);
        app(RentalOverrideService::class)->preview($this->actor, $assignment, [
            'period'=>'2026-11','override_type'=>'daily_amount','override_daily_amount'=>'100.00',
        ]);
    }

    public function test_monthly_scheduler_skips_fixed_agreements_and_dashboard_counts_weekly_charges()
    {
        $assignment = $this->assign();
        $this->artisan('billing:draft', ['period'=>'2026-11','--due'=>'2026-11-30','--actor'=>$this->actor->id])->assertExitCode(0);
        $this->assertDatabaseCount('rental_bills', 0);
        $service = app(BillingService::class);
        $bill = $service->generate($this->actor, $assignment, '2026-11-08', '2026-11-14');
        $service->post($this->actor, $bill);
        $this->assertSame('1400.00', \App\Services\Ledger::dashboard(null, '2026-11-01', '2026-11-30')['billed']);
        $this->assertSame('0.00', \App\Services\Ledger::dashboard(null, '2026-10-01', '2026-10-31')['billed']);
    }

    public function test_fixed_day_charge_does_not_overflow_due_to_unassessed_advance()
    {
        $this->stall->update(['area'=>'1.00']);
        BusinessCategoryRentalRate::query()->update(['minimum_rate'=>'100000000000.00','maximum_rate'=>'100000000000.00']);
        $assignment = $this->assign(['daily_cusa'=>'0.00']);
        $this->assertSame('700000000000.00', $assignment->agreement->monthly_amount);
        $this->assertSame('0.00', $assignment->agreement->assessment->advance_rental);
    }

    public function test_same_month_past_interval_requires_backdate_permission()
    {
        $assignment = $this->assign();
        $this->actor->syncRoles([]);
        $this->actor->givePermissionTo('billing.generate');
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(BillingService::class)->generate($this->actor, $assignment, '2026-11-22', '2026-11-28');
    }

    public function test_existing_overlapping_coverage_is_rejected_without_a_second_assessment()
    {
        $assignment = $this->assign();
        RentalBill::create(['reference'=>'SYNTHETIC-OVERLAP','stall_assignment_id'=>$assignment->id,
            'tenant_id'=>$this->tenant->id,'stall_id'=>$this->stall->id,'period'=>'2026-11-03',
            'coverage_start'=>'2026-11-03','coverage_end'=>'2026-11-09','due_date'=>'2026-11-09',
            'amount'=>'1400.00','status'=>'draft','created_by'=>$this->actor->id]);
        try {
            app(BillingService::class)->generate($this->actor, $assignment, '2026-11-08', '2026-11-14');
            $this->fail('An overlapping bill was generated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing', $exception->errors());
            $this->assertDatabaseCount('rental_bills', 1);
            $this->assertDatabaseCount('rental_assessments', 1);
        }
    }

    public function test_interval_is_adjustable_by_renewal_without_rewriting_original_terms()
    {
        $assignment = $this->assign();
        $initial = $assignment->agreement->assessment->getRawOriginal();
        $renewed = app(AssignmentService::class)->transfer($this->actor, $assignment, [
            'stall_id'=>$this->stall->id,'business_id'=>$this->business->id,'start_date'=>'2026-11-08',
            'billing_interval_days'=>14,'daily_cusa'=>'3.21','reason'=>'Fictional interval adjustment',
            'agreement_reference'=>'FIX-RENEWAL',
        ]);
        $this->assertSame(14, $renewed->agreement->assessment->billing_days);
        $this->assertSame('2800.00', $renewed->agreement->monthly_amount);
        $this->assertSame($initial, $assignment->agreement->assessment->fresh()->getRawOriginal());
        $bill = app(BillingService::class)->generate($this->actor, $renewed, '2026-11-08', '2026-11-21');
        $this->assertSame('2026-11-21', $bill->coverage_end);
    }

    public function test_fixed_day_snapshot_cannot_generate_a_monthly_contract_assessment()
    {
        $assignment = $this->assign();
        $this->expectException(ValidationException::class);
        app(RentalCalculationService::class)->assess($this->actor, $assignment, '2026-11-01', 'contract');
    }

    public function test_concurrent_fixed_assignment_retains_the_policy_used_for_occupancy_authority()
    {
        $first = $this->assign();
        $policy = RentalPolicy::create(['market_id'=>$this->stall->market_id,'daily_cusa'=>'99.00','billing_day_rule'=>'fixed',
            'fixed_billing_days'=>30,'max_concurrent_assignments'=>2,'advance_deposit_authorized'=>true,
            'status'=>'approved','effective_from'=>'2020-01-01','approved_by'=>$this->actor->id,'approved_at'=>now(),
            'created_by'=>$this->actor->id,'reference_document'=>'SYNTHETIC CONCURRENT AUTHORITY']);
        $otherStall = Stall::create(array_replace($this->stall->only([
            'market_id','market_section_id','classification','location_type','area',
        ]), ['code'=>'FIX-2','status'=>'vacant']));
        $second = $this->assign(['stall_id'=>$otherStall->id,'agreement_reference'=>'FIX-CONCURRENT',
            'concurrent_authorization_reference'=>'SYNTHETIC ONLY','concurrent_authorization_reason'=>'Fictional occupancy approval']);
        $this->assertNull($first->agreement->assessment->rental_policy_id);
        $assessment = $second->agreement->assessment;
        $this->assertSame($policy->id, (int) $assessment->rental_policy_id);
        $this->assertSame('SYNTHETIC CONCURRENT AUTHORITY', $assessment->basis['policy_reference']);
        $this->assertSame('1400.00', $assessment->total_monthly_rental_cusa);
        $this->assertSame('10.00', $assessment->daily_cusa);
    }

    public function test_switch_off_renewal_requires_a_month_boundary_as_well_as_old_interval_boundary()
    {
        $assignment = $this->assign();
        config(['billing.fixed_day_enabled'=>false]);
        RentalPolicy::create(['market_id'=>$this->stall->market_id,'daily_cusa'=>'0.00','billing_day_rule'=>'fixed',
            'fixed_billing_days'=>30,'max_concurrent_assignments'=>1,'advance_deposit_authorized'=>true,
            'status'=>'approved','effective_from'=>'2020-01-01','approved_by'=>$this->actor->id,'approved_at'=>now(),
            'created_by'=>$this->actor->id,'reference_document'=>'SYNTHETIC ONLY']);
        try {
            app(AssignmentService::class)->transfer($this->actor, $assignment, [
                'stall_id'=>$this->stall->id,'business_id'=>$this->business->id,'start_date'=>'2026-11-08',
                'reason'=>'Fictional return to monthly billing','agreement_reference'=>'FIX-MONTHLY-RENEWAL',
            ]);
            $this->fail('A mid-month monthly renewal was created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment', $exception->errors());
            $this->assertSame('active', $assignment->fresh()->status);
            $this->assertDatabaseCount('stall_assignments', 1);
        }
    }
}
