<?php

namespace Tests\Feature;

use App\Models\{Business, BusinessCategory, BusinessCategoryRentalRate, Market, MarketSection, RentalBill, RentalPolicy, Stall, Tenant, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FixedDayBillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private $actor;
    private $market;
    private $section;
    private $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 25));
        $this->seed(RoleSeeder::class);
        $this->actor = User::create(['name'=>'Fictional billing officer','email'=>'fixed-day@example.test','password'=>'unused','active'=>true]);
        $this->actor->assignRole('Super Administrator');
        $this->actingAs($this->actor);
        $this->market = Market::create(['code'=>'WORKFLOW','name'=>'Fictional market']);
        $this->section = MarketSection::create(['market_id'=>$this->market->id,'code'=>'A','name'=>'Fictional section']);
        $this->category = BusinessCategory::create(['code'=>'WORKFLOW','name'=>'Fictional business category','is_active'=>true]);
        BusinessCategoryRentalRate::create($this->approval() + [
            'business_category_id'=>$this->category->id,'minimum_rate'=>'4.75','maximum_rate'=>'5.00',
        ]);
    }

    public function test_registration_preview_and_resumed_draft_use_the_same_fixed_days_and_record_only_cusa()
    {
        config(['billing.fixed_day_enabled'=>true]);
        $stall = $this->stall('WEEKLY');
        $selection = ['stall_id'=>$stall->id,'start_date'=>'2026-11-02','billing_interval_days'=>9,'daily_cusa'=>'3.25'];
        $preview = $selection + ['business_category_id'=>$this->category->id];
        $this->postJson(route('tenants.rental-preview'), array_replace($preview, ['billing_interval_days'=>367]))
            ->assertStatus(422)->assertJsonValidationErrors('billing_interval_days');
        $this->postJson(route('tenants.rental-preview'), $preview)->assertOk()
            ->assertJsonPath('billing_cycle', 'fixed_days')->assertJsonPath('cusa_chargeable', false)
            ->assertJsonPath('period_label', '2026-11-02 – 2026-11-10')
            ->assertJsonPath('billing_days', 9)->assertJsonPath('monthly_cusa', '29.25')
            ->assertJsonPath('total_monthly_rental_cusa', '213.75');

        $registration = [
            'profile_form'=>'1','status'=>'active','first_name'=>'Fictional','last_name'=>'Stallholder',
            'registration_action'=>'draft','submission_key'=>(string) Str::uuid(),
            'businesses'=>['fruit'=>['business_category_id'=>$this->category->id,'status'=>'active','permitted_goods'=>'Fictional fruit']],
            'pending_assignments'=>[$selection + ['business_key'=>'fruit']],
        ];
        $this->post('/manage/tenants', $registration)->assertSessionHasNoErrors()->assertRedirect();
        $tenant = Tenant::firstOrFail();
        $this->assertSame(9, (int) $tenant->registration_draft['assignments'][0]['billing_interval_days']);
        $this->assertSame('3.25', $tenant->registration_draft['assignments'][0]['daily_cusa']);
        $this->get('/manage/tenants/'.$tenant->id.'/edit')->assertOk()
            ->assertSee('name="pending_assignments[0][billing_interval_days]"', false)->assertSee('value="3.25"', false);

        $registration['businesses']['fruit']['id'] = $tenant->businesses()->firstOrFail()->id;
        $this->put('/manage/tenants/'.$tenant->id, array_replace($registration, [
            'registration_action'=>'confirm','submission_key'=>(string) Str::uuid(),
            'registration_version'=>$tenant->registration_draft['version'],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $assessment = $tenant->assignments()->firstOrFail()->agreement->assessment;
        $this->assertTrue($assessment->isFixedDays());
        $this->assertSame('213.75', $assessment->total_monthly_rental_cusa);
        $this->get('/manage/tenants/'.$tenant->id.'?tab=rental')->assertOk()
            ->assertSee('CUSA for records only')->assertDontSee('Manual override request');
    }

    public function test_blank_interval_uses_the_same_seven_days_in_preview_and_assignment_forms()
    {
        config(['billing.fixed_day_enabled'=>true]);
        $preview = ['stall_id'=>$this->stall('PREVIEW')->id,'business_category_id'=>$this->category->id,
            'start_date'=>'2026-11-02','billing_interval_days'=>''];
        $this->postJson(route('tenants.rental-preview'), $preview)->assertOk()
            ->assertJsonPath('billing_days', 7)->assertJsonPath('total_monthly_rental_cusa', '166.25');
        $assignment = $this->assignment('DEFAULT', '2026-11-02', ['billing_interval_days'=>'']);
        $this->assertSame(7, $assignment->agreement->assessment->billing_days);
        $this->assertSame('166.25', $assignment->agreement->monthly_amount);

        $this->post('/manage/tenants', [
            'profile_form'=>'1','status'=>'active','first_name'=>'Fictional','last_name'=>'Registration',
            'registration_action'=>'confirm','submission_key'=>(string) Str::uuid(),
            'businesses'=>['fruit'=>['business_category_id'=>$this->category->id,'status'=>'active','permitted_goods'=>'Fictional fruit']],
            'pending_assignments'=>[['business_key'=>'fruit','stall_id'=>$preview['stall_id'],
                'start_date'=>$preview['start_date'],'billing_interval_days'=>'']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $tenant = Tenant::where('last_name', 'Registration')->firstOrFail();
        $this->assertSame(7, $tenant->assignments()->firstOrFail()->agreement->assessment->billing_days);
    }

    public function test_native_forms_generate_separate_draft_periods_and_reject_mixed_modes_atomically()
    {
        $monthly = $this->assignment('MONTHLY', '2026-11-01');
        config(['billing.fixed_day_enabled'=>true]);
        $fixed = $this->assignment('WEEKLY', '2026-11-02', ['daily_cusa'=>'3.25']);
        $this->get(route('assignments.transfer-form', $monthly))->assertOk()
            ->assertSee('New start date (first day of a month)')->assertSee('Billing interval (days)');

        $this->get(route('billing.create'))->assertOk()->assertSee('MONTHLY')->assertDontSee('WEEKLY');
        $this->get(route('billing.create', ['billing_mode'=>'fixed_days']))->assertOk()
            ->assertSee('Fixed-day period start')->assertSee('WEEKLY')->assertDontSee('MONTHLY');
        $payload = ['billing_mode'=>'fixed_days','period_start'=>'2026-11-02','due_date'=>'2026-11-30'];
        $this->post(route('billing.generate'), $payload + ['assignment_ids'=>[$fixed->id, $monthly->id]])
            ->assertSessionHasErrors('period_start');
        $this->post(route('billing.generate'), ['billing_mode'=>'monthly','period'=>'2026-11','due_date'=>'2026-11-30',
            'assignment_ids'=>[$fixed->id, $monthly->id]])->assertSessionHasErrors('period');
        $this->assertSame(0, RentalBill::count());

        foreach (['2026-11-02','2026-11-09'] as $start) {
            $this->post(route('billing.generate'), array_replace($payload, ['period_start'=>$start,'assignment_ids'=>[$fixed->id]]))
                ->assertSessionHasNoErrors()->assertRedirect(route('billing.index', ['period_start'=>$start]));
        }
        $this->assertSame(2, RentalBill::where('status','draft')->count());
        $this->get(route('billing.index', ['period'=>'2026-11']))->assertOk()->assertSee('2026-11-08')->assertSee('2026-11-15');
        $this->get(route('billing.index', ['period_start'=>'2026-11-09']))->assertOk()->assertSee('2026-11-15')->assertDontSee('2026-11-08');
        $bill = RentalBill::where('period','2026-11-09')->firstOrFail();
        $this->get(route('billing.show', $bill))->assertOk()->assertSee('CUSA for records only')->assertSee('2026-11-15');

        config(['billing.fixed_day_enabled'=>false]);
        $this->get(route('assignments.create'))->assertOk()->assertDontSee('Billing interval');
        $this->get(route('assignments.transfer-form', $fixed))->assertOk()
            ->assertSee('Use a 7-day interval boundary after the original start on 2026-11-02.')
            ->assertSee('The new monthly term must also start on the first day of a month.')
            ->assertDontSee('Billing interval (days)');
        $this->get(route('billing.create', ['billing_mode'=>'fixed_days']))->assertOk()->assertSee('WEEKLY');
        $this->post(route('billing.generate'), array_replace($payload, ['period_start'=>'2026-11-16','assignment_ids'=>[$fixed->id]]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('billing.generate'), ['assignment_ids'=>[$monthly->id],'period'=>'2026-11','due_date'=>'2026-12-01'])
            ->assertSessionHasNoErrors()->assertRedirect(route('billing.index', ['period'=>'2026-11']));
        $this->post(route('billing.post', $bill))->assertSessionHasNoErrors();
        $this->get(route('reports.show', ['type'=>'monthly-billing','format'=>'print']))->assertOk()
            ->assertSee('2026-11-09')->assertSee('2026-11-15');
        $this->get(route('reports.show', ['type'=>'rental-assessments','format'=>'print']))->assertOk()
            ->assertSee('Records only')->assertSee('2026-11-15');
    }

    private function assignment(string $code, string $start, array $terms = [])
    {
        $stall = $this->stall($code);
        $tenant = Tenant::create(['reference'=>$code,'name'=>'Fictional '.$code,'status'=>'active']);
        $business = Business::create(['tenant_id'=>$tenant->id,'business_category_id'=>$this->category->id,
            'status'=>'active','permitted_goods'=>'Fictional fruit']);
        if (!config('billing.fixed_day_enabled')) {
            RentalPolicy::create($this->approval() + ['market_id'=>$this->market->id,'daily_cusa'=>'1.00',
                'billing_day_rule'=>'calendar','max_concurrent_assignments'=>1,'advance_deposit_authorized'=>false]);
        }
        $this->post(route('assignments.store'), $terms + ['tenant_id'=>$tenant->id,'business_id'=>$business->id,
            'stall_id'=>$stall->id,'start_date'=>$start,'agreement_reference'=>'AGREE-'.$code])
            ->assertSessionHasNoErrors()->assertRedirect();
        return $tenant->assignments()->firstOrFail();
    }

    private function stall(string $code)
    {
        return Stall::create(['market_id'=>$this->market->id,'market_section_id'=>$this->section->id,
            'code'=>$code,'classification'=>'Fictional','area'=>'5.00','location_type'=>'inner','status'=>'vacant']);
    }

    private function approval(): array
    {
        return ['status'=>'approved','effective_from'=>'2026-01-01','reference_document'=>'FICTIONAL-REF',
            'created_by'=>$this->actor->id,'approved_by'=>$this->actor->id,'approved_at'=>now()];
    }
}
