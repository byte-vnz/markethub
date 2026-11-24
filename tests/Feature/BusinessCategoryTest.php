<?php

namespace Tests\Feature;

use App\Models\{Business, BusinessCategory, BusinessCategoryRentalRate, Market, MarketSection, RentalAssessment, RentalPolicy, Stall, StallAssignment, Tenant, User};
use App\Services\BusinessCategoryService;
use Database\Seeders\BusinessCategorySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BusinessCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_rates_are_exact_undated_and_repeatable_without_overwriting_history()
    {
        $this->assertTrue(Schema::hasTable('business_categories'));
        $this->seed(BusinessCategorySeeder::class);
        $this->assertSame(21, BusinessCategory::count());
        $this->assertSame(21, BusinessCategoryRentalRate::count());
        $this->assertSame(21, BusinessCategoryRentalRate::where('status', 'draft')
            ->whereNull('effective_from')->whereNull('effective_until')
            ->whereNull('approved_at')->whereNull('approved_by')->count());
        $it = BusinessCategory::where('code', 'IT-EQUIPMENT')->firstOrFail();
        $fruits = BusinessCategory::where('code', 'FRUITS')->firstOrFail()->rentalRates()->firstOrFail();
        $this->assertSame('Other IT related equipments/items', $it->name);
        $this->assertSame('34.84', $it->rentalRates()->firstOrFail()->minimum_rate);
        $this->assertSame('40.00', $it->rentalRates()->firstOrFail()->maximum_rate);
        $this->assertSame('30.97', $fruits->minimum_rate);
        $this->assertSame('30.97', $fruits->maximum_rate);
        $this->assertSame('Bakery, carinderia, food kiosks, and other cooked/prepared food ready for consumption.', BusinessCategory::where('code', 'PREPARED-FOOD')->value('description'));
        $it->update(['name' => 'Approved local label']);
        $it->rentalRates()->update(['status' => 'approved', 'effective_from' => '2027-01-01', 'maximum_rate' => '41.00']);

        $this->seed(BusinessCategorySeeder::class);

        $this->assertSame(21, BusinessCategory::count());
        $this->assertSame(21, BusinessCategoryRentalRate::count());
        $this->assertSame(0, RentalPolicy::count());
        $this->assertSame('Approved local label', $it->fresh()->name);
        $rate = $it->rentalRates()->firstOrFail();
        $this->assertSame('41.00', $rate->maximum_rate);
        $this->assertSame('approved', $rate->status);
        $this->assertSame('2027-01-01', $rate->effective_from->toDateString());
    }

    public function test_seeder_repairs_unapproved_it_seed_with_a_missing_amount_only()
    {
        $this->seed(BusinessCategorySeeder::class);
        $it = BusinessCategory::where('code', 'IT-EQUIPMENT')->firstOrFail();
        $cellphone = BusinessCategory::where('code', 'CELLPHONE')->firstOrFail()->rentalRates()->firstOrFail();
        $rate = $it->rentalRates()->firstOrFail();
        $approved = $it->rentalRates()->create(['status' => 'approved', 'minimum_rate' => '41.00', 'maximum_rate' => '43.00',
            'effective_from' => '2099-01-01', 'approved_at' => now(), 'approved_by' => $this->actor('approved-seed')->id]);
        $rate->update(['minimum_rate' => null]);
        $this->seed(BusinessCategorySeeder::class);
        $this->assertSame($cellphone->minimum_rate, $rate->fresh()->minimum_rate);
        $this->assertSame($cellphone->maximum_rate, $rate->fresh()->maximum_rate);
        $this->assertSame('draft', $rate->fresh()->status);
        $this->assertSame('41.00', $approved->fresh()->minimum_rate);

        $rate->refresh()->update(['minimum_rate' => null, 'created_by' => $this->actor('manual-draft')->id]);
        $this->seed(BusinessCategorySeeder::class);
        $this->assertNull($rate->fresh()->minimum_rate);
    }

    public function test_optional_commercial_name_nullable_location_and_encrypted_hidden_draft()
    {
        $this->assertTrue(Schema::hasColumn('tenants', 'registration_draft'));
        $market = $this->market();
        $section = MarketSection::create(['market_id' => $market->id, 'name' => 'Fictional section', 'code' => 'S']);
        $stall = Stall::create(['market_id' => $market->id, 'market_section_id' => $section->id, 'code' => 'S-01', 'classification' => 'Legacy', 'area' => '5.00']);
        $tenant = Tenant::create(['reference' => 'DRAFT-1', 'name' => 'Fictional tenant', 'status' => 'draft', 'registration_draft' => ['stall_id' => $stall->id]]);
        $business = Business::create(['tenant_id' => $tenant->id, 'name' => null]);

        $this->assertNull($business->fresh()->name);
        $this->assertNull($stall->fresh()->location_type);
        $this->assertSame(['stall_id' => $stall->id], $tenant->fresh()->registration_draft);
        $this->assertStringNotContainsString('stall_id', $tenant->getRawOriginal('registration_draft'));
        $this->assertArrayNotHasKey('registration_draft', $tenant->toArray());
    }

    public function test_additive_migration_preserves_linked_legacy_businesses_and_foreign_keys()
    {
        $previousConnection = DB::getDefaultConnection();
        $connection = 'category_migration_fixture';
        config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true, 'prefix' => '']]);
        DB::setDefaultConnection($connection);
        try {
            (new \CreateUsersTable)->up();
            (new \CreateMarketSchema)->up();
            (new \EnhanceTenantProfiles)->up();
            $actor = $this->actor('migration');
            $market = $this->market();
            $section = MarketSection::create(['market_id' => $market->id, 'name' => 'Fictional section', 'code' => 'S']);
            $stall = Stall::create(['market_id' => $market->id, 'market_section_id' => $section->id, 'code' => 'LEGACY', 'classification' => 'Legacy', 'area' => '5.00']);
            $tenant = Tenant::create(['reference' => 'LEGACY-1', 'name' => 'Fictional tenant', 'status' => 'active']);
            $business = Business::create(['tenant_id' => $tenant->id, 'name' => 'Original trade name', 'category' => 'Legacy trade', 'permit_reference' => 'Old permit']);
            // Raw legacy inserts avoid caching pre-upgrade Eloquent column guards for later tests.
            $assignmentId = DB::table('stall_assignments')->insertGetId(['business_id' => $business->id, 'tenant_id' => $tenant->id, 'stall_id' => $stall->id,
                'active_stall_id' => $stall->id, 'start_date' => '2026-01-01', 'authorized_by' => $actor->id]);
            $legacy = $business->fresh()->getAttributes();
            Schema::disableForeignKeyConstraints();
            $orphan = Business::create(['tenant_id' => 99999, 'name' => 'Fictional orphan']);
            Schema::enableForeignKeyConstraints();

            DB::statement('CREATE INDEX stalls_location_type_index ON stalls (code)');
            try {
                (new \UnifyBusinessCategories)->up();
                $this->fail('The existing index should reject a duplicate name.');
            } catch (\Illuminate\Database\QueryException $failure) {
                $this->assertStringContainsString('stalls_location_type_index', $failure->getMessage());
            }
            $this->assertFalse(Schema::hasTable('business_categories'));
            $this->assertFalse(Schema::hasColumn('businesses', 'business_category_id'));
            $this->assertFalse(Schema::hasColumn('stalls', 'location_type'));
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
            DB::statement('DROP INDEX stalls_location_type_index');

            (new \UnifyBusinessCategories)->up();

            $this->assertSame($legacy, array_intersect_key($business->fresh()->getAttributes(), $legacy));
            $this->assertSame($business->id, DB::table('stall_assignments')->where('id',$assignmentId)->value('business_id'));
            $this->assertNull($stall->fresh()->location_type);
            $this->assertNull($tenant->fresh()->registration_draft);
            $this->assertSame(99999, $orphan->fresh()->tenant_id);
            $violations = DB::select('PRAGMA foreign_key_check');
            $this->assertCount(1, $violations);
            $this->assertSame(['businesses', $orphan->id, 'tenants'], [$violations[0]->table, $violations[0]->rowid, $violations[0]->parent]);
            $this->expectException(\Illuminate\Database\QueryException::class);
            $business->delete();
        } finally {
            DB::purge($connection);
            DB::setDefaultConnection($previousConnection);
        }
    }

    public function test_category_settings_check_view_manage_approve_and_active_account_separately()
    {
        $path = '/settings/business-categories';
        $this->get($path)->assertRedirect('/login');
        $this->actingAs($this->actor('no-privileges'))->get($path)->assertForbidden();
        $this->actingAs($this->actor('viewer', ['rates.view']))->get($path)->assertOk();
        $this->post($path, ['name' => 'Fictional'])->assertForbidden();
        $manager = $this->actor('manager', ['rates.view', 'rates.manage']);
        $this->actingAs($manager)->post($path, ['code' => 'SYN', 'name' => 'Fictional', 'sort_order' => 1, 'is_active' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $category = BusinessCategory::where('code', 'SYN')->firstOrFail();
        $this->post($path.'/'.$category->id.'/rates', $this->rateData())->assertSessionHasNoErrors()->assertRedirect();
        $rate = $category->rentalRates()->firstOrFail();
        $this->post($path.'/'.$category->id.'/rates/'.$rate->id.'/approve')->assertForbidden();
        $this->assertSame('draft', $rate->fresh()->status);
        $manager->update(['active' => false]);
        $this->get($path)->assertRedirect('/login');
    }

    public function test_approval_rejects_invalid_and_inclusive_overlapping_rates_and_preserves_approved_values()
    {
        $this->actingAs($this->actor('approver', ['rates.view', 'rates.manage', 'rates.approve']));
        $category = BusinessCategory::create(['code' => 'SYN', 'name' => 'Synthetic']);
        $other = BusinessCategory::create(['code' => 'OTHER', 'name' => 'Other category']);
        $path = '/settings/business-categories/'.$category->id.'/rates';
        $this->post($path, $this->rateData(['minimum_rate' => '40.01']))->assertSessionHasErrors('maximum_rate');
        $this->post($path, $this->rateData(['maximum_rate' => '40.001']))->assertSessionHasErrors('maximum_rate');
        $this->post($path, $this->rateData(['effective_until' => '2099-01-01']))->assertSessionHasErrors('effective_until');
        $this->post($path, $this->rateData(['reference_document' => null]))->assertSessionHasNoErrors();
        $first = $category->rentalRates()->firstOrFail();
        $this->post($path.'/'.$first->id.'/approve', $this->approvalPayload($first))->assertSessionHasErrors('reference_document');
        $this->put($path.'/'.$first->id, $this->rateData())->assertSessionHasNoErrors();
        $this->post($path.'/'.$first->id.'/approve', $this->approvalPayload($first))->assertSessionHasNoErrors();
        $this->put($path.'/'.$first->id, $this->rateData(['maximum_rate' => '42.00']))->assertSessionHasErrors('status');
        $this->post('/settings/business-categories/'.$other->id.'/rates/'.$first->id.'/approve', $this->approvalPayload($first))->assertNotFound();
        $this->assertSame('40.00', $first->fresh()->maximum_rate);
        $this->post($path, $this->rateData(['effective_from' => '2099-01-31', 'effective_until' => null]))->assertSessionHasNoErrors();
        $second = $category->rentalRates()->latest('id')->firstOrFail();
        $this->post($path.'/'.$second->id.'/approve', $this->approvalPayload($second))->assertSessionHasErrors('effective_from');
        $this->post($path.'/'.$first->id.'/retire', ['effective_until' => '2099-01-30'])->assertSessionHasErrors('reason');
        $this->post($path.'/'.$first->id.'/retire', ['effective_until' => '2099-02-01', 'reason' => 'Superseded rate'])->assertSessionHasErrors('effective_until');
        $this->post($path.'/'.$first->id.'/retire', ['effective_until' => '2099-01-30', 'reason' => 'Superseded rate'])->assertSessionHasNoErrors();
        $this->assertSame('retired', $first->fresh()->status);
        $this->post($path.'/'.$second->id.'/approve', $this->approvalPayload($second))->assertSessionHasNoErrors();
        $this->assertSame('approved', $second->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'BusinessCategoryRentalRate', 'entity_id' => $first->id, 'reason' => 'Superseded rate']);
        $this->get('/settings/business-categories/'.$category->id.'/edit')->assertOk()->assertSee('2099-01-30');
    }

    public function test_market_policy_requires_explicit_cusa_and_valid_day_rule_and_has_versioned_history()
    {
        $this->actingAs($this->actor('approver', ['rates.view', 'rates.manage', 'rates.approve']));
        $path = '/settings/rental-policies';
        $policy = ['market_id' => $this->market()->id, 'daily_cusa' => '0.00', 'billing_day_rule' => 'calendar',
            'max_concurrent_assignments' => 1, 'advance_deposit_authorized' => 1, 'effective_from' => '2099-01-01',
            'reference_document' => 'Synthetic authorizing document'];
        $this->post($path, array_replace($policy, ['daily_cusa' => null]))->assertSessionHasErrors('daily_cusa');
        $this->post($path, array_replace($policy, ['billing_day_rule' => 'fixed']))->assertSessionHasErrors('fixed_billing_days');
        $this->post($path, array_replace($policy, ['fixed_billing_days' => 30]))->assertSessionHasErrors('fixed_billing_days');
        $this->post($path, $policy)->assertSessionHasNoErrors();
        $first = RentalPolicy::firstOrFail();
        $this->post($path.'/'.$first->id.'/approve', $this->approvalPayload($first))->assertSessionHasNoErrors();
        $this->assertSame('0.00', $first->fresh()->daily_cusa);
        $this->put($path.'/'.$first->id, $policy)->assertSessionHasErrors('status');
        $this->post($path, array_replace($policy, ['effective_from' => '2099-02-01', 'billing_day_rule' => 'fixed', 'fixed_billing_days' => 30]))->assertSessionHasNoErrors();
        $second = RentalPolicy::latest('id')->firstOrFail();
        $this->post($path.'/'.$second->id.'/approve', $this->approvalPayload($second))->assertSessionHasErrors('effective_from');
        $this->post($path.'/'.$first->id.'/retire', ['effective_until' => '2099-01-31', 'reason' => 'New official policy'])->assertSessionHasNoErrors();
        $this->post($path.'/'.$second->id.'/approve', $this->approvalPayload($second))->assertSessionHasNoErrors();
        $this->get($path)->assertOk()->assertSee('Synthetic authorizing document')->assertSee('2099-01-31');
    }

    public function test_retirement_cannot_exclude_the_commencement_of_a_frozen_assessment()
    {
        $actor = $this->actor('retire', ['rates.view', 'rates.manage', 'rates.approve']);
        $this->actingAs($actor);
        $category = BusinessCategory::create(['code' => 'SYN', 'name' => 'Synthetic']);
        $market = $this->market();
        $policy = RentalPolicy::create(['market_id' => $market->id, 'daily_cusa' => '0.00', 'billing_day_rule' => 'calendar',
            'max_concurrent_assignments' => 1, 'advance_deposit_authorized' => true, 'effective_from' => '2099-01-01',
            'status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id, 'reference_document' => 'Synthetic policy reference']);
        $rate = $category->rentalRates()->create($this->rateData(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id]));
        $section = MarketSection::create(['market_id' => $market->id, 'name' => 'Fictional section', 'code' => 'S']);
        $stall = Stall::create(['market_id' => $market->id, 'market_section_id' => $section->id, 'code' => 'S-01', 'classification' => 'Synthetic', 'area' => '5.00', 'location_type' => 'inner']);
        $tenant = Tenant::create(['reference' => 'SYN-1', 'name' => 'Fictional tenant', 'status' => 'active']);
        $business = Business::create(['tenant_id' => $tenant->id, 'business_category_id' => $category->id]);
        $assignment = StallAssignment::create(['business_id' => $business->id, 'tenant_id' => $tenant->id, 'stall_id' => $stall->id,
            'active_stall_id' => $stall->id, 'start_date' => '2099-01-20', 'authorized_by' => $actor->id]);
        $assessment = RentalAssessment::create([
            'stall_assignment_id' => $assignment->id, 'business_id' => $business->id, 'business_category_id' => $category->id,
            'business_category_rental_rate_id' => $rate->id, 'rental_policy_id' => $policy->id, 'market_id' => $market->id, 'created_by' => $actor->id,
            'period' => '2099-01', 'purpose' => 'initial', 'effective_from' => '2099-01-20', 'market_name' => $market->name,
            'stall_code' => $stall->code, 'business_category_name' => $category->name, 'location_type' => 'inner', 'billing_days' => 31,
            'floor_area' => '5.00', 'minimum_rate' => '34.84', 'maximum_rate' => '40.00', 'rental_rate_sqm' => '34.84', 'daily_cusa' => '0.00',
            'daily_rental' => '174.20', 'monthly_rental' => '5400.20', 'monthly_cusa' => '0.00', 'total_monthly_rental_cusa' => '5400.20',
            'advance_rental' => '10800.40', 'security_deposit' => '5400.20', 'total_initial_payment' => '16200.60', 'basis' => [], 'snapshot' => [],
        ]);
        $requested = ['effective_until' => '2099-01-19', 'reason' => 'Proposed successor'];

        $this->post('/settings/business-categories/'.$category->id.'/rates/'.$rate->id.'/retire', $requested)->assertSessionHasErrors('effective_until');
        $this->post('/settings/rental-policies/'.$policy->id.'/retire', $requested)->assertSessionHasErrors('effective_until');
        $this->assertSame('approved', $rate->fresh()->status);
        $this->assertSame('approved', $policy->fresh()->status);
        $this->assertSame('2099-01-20', $assessment->fresh()->effective_from);
    }

    public function test_stale_approval_forms_reject_same_second_rate_and_policy_edits()
    {
        $this->travelTo(now()->startOfSecond());
        $category = BusinessCategory::create(['code' => 'SYN', 'name' => 'Synthetic']);
        $rateData = $this->rateData();
        $rate = $category->rentalRates()->create($rateData);
        $policyData = ['market_id' => $this->market()->id, 'daily_cusa' => '0.00', 'billing_day_rule' => 'calendar',
            'max_concurrent_assignments' => 1, 'advance_deposit_authorized' => 1, 'effective_from' => '2099-01-01',
            'reference_document' => 'Synthetic authorizing document'];
        $policy = RentalPolicy::create($policyData);
        $ratePage = '/settings/business-categories/'.$category->id.'/edit';
        $ratePath = '/settings/business-categories/'.$category->id.'/rates/'.$rate->id;
        $policyPage = '/settings/rental-policies';
        $policyPath = $policyPage.'/'.$policy->id;
        $approver = $this->actor('stale-approver', ['rates.view', 'rates.approve']);
        $manager = $this->actor('stale-manager', ['rates.view', 'rates.manage']);

        $this->actingAs($approver);
        $oldRate = $this->approvalFormPayload($ratePage);
        $oldPolicy = $this->approvalFormPayload($policyPage);

        $this->actingAs($manager);
        $this->put($ratePath, array_replace($rateData, ['maximum_rate' => '42.00']))->assertSessionHasNoErrors();
        $this->put($policyPath, array_replace($policyData, ['daily_cusa' => '2.00']))->assertSessionHasNoErrors();
        $this->assertSame($rate->updated_at->toDateTimeString(), $rate->fresh()->updated_at->toDateTimeString());
        $this->assertSame($policy->updated_at->toDateTimeString(), $policy->fresh()->updated_at->toDateTimeString());

        $this->actingAs($approver);
        $this->post($ratePath.'/approve')->assertSessionHasErrors('draft_fingerprint');
        $this->post($ratePath.'/approve', $oldRate)->assertSessionHasErrors('draft_fingerprint');
        $this->post($policyPath.'/approve', $oldPolicy)->assertSessionHasErrors('draft_fingerprint');
        $this->assertSame('draft', $rate->fresh()->status);
        $this->assertSame('draft', $policy->fresh()->status);
        $this->post($ratePath.'/approve', $this->approvalFormPayload($ratePage))->assertSessionHasNoErrors();
        $this->post($policyPath.'/approve', $this->approvalFormPayload($policyPage))->assertSessionHasNoErrors();
        $this->assertSame('approved', $rate->fresh()->status);
        $this->assertSame('approved', $policy->fresh()->status);
    }

    private function approvalFormPayload(string $page): array
    {
        $html = $this->get($page)->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="draft_fingerprint" value="([a-f0-9]{64})"/', $html, $match));
        return ['draft_fingerprint' => $match[1]];
    }

    private function approvalPayload(Model $schedule): array
    {
        return ['draft_fingerprint' => BusinessCategoryService::draftFingerprint($schedule->fresh())];
    }

    private function actor(string $label, array $permissions = []): User
    {
        $user = User::create(['name' => 'Synthetic user', 'email' => $label.'@example.test', 'password' => 'unused', 'active' => true]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }
        return $user;
    }

    private function market(): Market
    {
        return Market::create(['code' => 'SYN', 'name' => 'Fictional market']);
    }

    private function rateData(array $overrides = []): array
    {
        return $overrides + ['minimum_rate' => '34.84', 'maximum_rate' => '40.00', 'effective_from' => '2099-01-02',
            'effective_until' => '2099-01-31', 'reference_document' => 'Synthetic rate reference'];
    }
}
