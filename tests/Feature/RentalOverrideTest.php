<?php

namespace Tests\Feature;

use App\Models\{BusinessCategoryRentalRate, Market, MarketSection, RentalOverride, RentalPolicy, Stall, Tenant, User};
use App\Services\{AssignmentService, BillingService, BusinessCategoryService, RentalCalculationService, RentalOverrideService};
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\ApprovedRentalConfiguration;
use Tests\TestCase;

class RentalOverrideTest extends TestCase
{
    use RefreshDatabase, ApprovedRentalConfiguration;

    private $actor;
    private $reviewer;
    private $assignment;
    private $policy;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2030-01-01');
        $this->seed(RoleSeeder::class);
        foreach (['actor', 'reviewer'] as $key) {
            $this->$key = User::create(['name' => $key, 'email' => $key.'@example.test', 'password' => 'unused', 'active' => true]);
            $this->$key->assignRole('Super Administrator');
        }
        $tenant = Tenant::create(['name' => 'Synthetic tenant', 'reference' => 'T', 'status' => 'active']);
        $market = Market::create(['name' => 'Fictional market', 'code' => 'M', 'status' => 'active']);
        $section = MarketSection::create(['name' => 'Fictional section', 'code' => 'S', 'market_id' => $market->id, 'status' => 'active']);
        $stall = Stall::create(['market_id' => $market->id, 'market_section_id' => $section->id, 'code' => 'A', 'area' => '10.00',
            'classification' => 'standard', 'location_type' => 'outer', 'status' => 'vacant']);
        $business = $this->businessWithApprovedRental($this->actor, $tenant, $market->id);
        BusinessCategoryRentalRate::query()->update(['minimum_rate' => '21.04', 'maximum_rate' => '26.88']);
        $this->assignment = app(AssignmentService::class)->assign($this->actor, ['business_id' => $business->id,
            'tenant_id' => $tenant->id, 'stall_id' => $stall->id, 'start_date' => '2029-12-01', 'agreement_reference' => 'A']);
        $this->policy = RentalPolicy::firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function data(array $changes = []): array
    {
        return array_replace(['override_type' => 'rate_per_sqm', 'override_rate_per_sqm' => '25.00',
            'effective_from' => '2030-02-01', 'reason' => 'Synthetic terms', 'submission_key' => (string) Str::uuid()], $changes);
    }

    private function service()
    {
        return app(RentalOverrideService::class);
    }

    private function pending(array $changes = [])
    {
        $draft = $this->service()->saveDraft($this->actor, $this->assignment, $this->data($changes));
        return $this->service()->submit($this->actor, $draft);
    }

    private function approve($request)
    {
        return $this->service()->approve($this->reviewer, $request, RentalOverrideService::fingerprint($request));
    }

    private function invalid($key, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Expected validation of ".$key);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), json_encode($e->errors()));
        }
    }

    public function test_request_permissions_and_safe_automatic_defaults()
    {
        $this->assertTrue($this->actor->can('rentals.override.approve'));
        $this->assertFalse($this->policy->allow_out_of_range_overrides);
        $this->assertFalse($this->policy->allow_zero_rent_overrides);
        $this->assertSame('automatic', $this->assignment->agreement->assessment->computation_mode);
        $this->assertNull($this->assignment->agreement->assessment->rental_override_id);
    }

    public function test_separate_approval_uses_frozen_automatic_basis_and_monthly_boundaries()
    {
        $calc = app(RentalCalculationService::class);
        $this->assertSame('automatic', $calc->forAssignment($this->assignment, '2030-02')['computation_mode']);
        $d = $this->data(['effective_until' => '2030-04-30']);
        $draft = $this->service()->saveDraft($this->actor, $this->assignment, $d);
        $this->assertSame('250.00', $draft->proposed_daily_rental);
        $this->assertSame($draft->id, $this->service()->saveDraft($this->actor, $this->assignment, $d)->id);
        $request = $this->service()->submit($this->actor, $draft);
        $this->assertSame('pending', $this->service()->submit($this->actor, $draft)->status);
        $fp = RentalOverrideService::fingerprint($request);
        $this->invalid('approved_by', fn () => $this->service()->approve($this->actor, $request, $fp));
        $this->invalid('fingerprint', fn () => $this->service()->approve($this->reviewer, $request, str_repeat('0', 64)));
        $this->assertSame('268.80', $calc->forAssignment($this->assignment, '2030-02')['daily_rental']);
        $approved = $this->approve($request);
        $this->assertSame($approved->id, $this->service()->approve($this->reviewer, $request, $fp)->id);
        $this->assertSame('expired', $approved->displayStatus('2030-05-01'));
        $this->assignment->stall->update(['area' => '11.00']);
        $this->policy->update(['daily_cusa' => '55.00']);
        BusinessCategoryRentalRate::query()->update(['maximum_rate' => '33.00']);
        $basis = $calc->forAssignment($this->assignment, '2030-02');
        $this->assertSame(['268.80','250.00','-18.80','7800.00'], [$basis['automatic_daily_rental'],
            $basis['daily_rental'], $basis['rental_difference'], $basis['total_monthly_rental_cusa']]);
        $revoked = $this->service()->revoke($this->actor, $approved, ['revocation_effective_from' => '2030-03-01', 'reason' => 'Return to automatic']);
        $this->assertSame('approved', $revoked->displayStatus('2030-02-01'));
        $this->assertSame('revoked', $revoked->displayStatus('2030-03-01'));
        Carbon::setTestNow('2030-03-15');
        $bill = app(BillingService::class)->generate($this->actor, $this->assignment, '2030-02', '2030-02-20');
        $this->assertSame('7800.00', $bill->amount);
        $this->assertEquals($approved->id, $bill->assessment->rental_override_id);
        $this->assertSame($this->reviewer->id, $bill->assessment->basis['override']['approved_by']);
        $this->assertSame('8364.00', $calc->forAssignment($this->assignment, '2030-03')['total_monthly_rental_cusa']);
        $this->assertSame('268.80', $this->assignment->agreement->assessment->daily_rental);
    }

    public function test_direct_daily_needs_explicit_authority_and_never_multiplies_by_area()
    {
        $request = $this->pending(['override_type' => 'daily_amount', 'override_rate_per_sqm' => null,
            'override_daily_amount' => '200', 'reference_document' => 'Test exception']);
        $this->invalid('override_authority', fn () => $this->approve($request));
        $before = RentalOverrideService::fingerprint($request);
        $this->policy->update(['allow_out_of_range_overrides' => true, 'override_authority_reference' => 'TEST-POLICY']);
        $this->invalid('fingerprint', fn () => $this->service()->approve($this->reviewer, $request, $before));
        $this->approve($request);
        $b = app(RentalCalculationService::class)->forAssignment($this->assignment, '2030-02');
        $this->assertSame(['200.00','-68.80','6300.00','12000.00','6000.00','18000.00','20.00',true], [
            $b['daily_rental'], $b['rental_difference'], $b['total_monthly_rental_cusa'], $b['advance_rental'],
            $b['security_deposit'], $b['total_initial_payment'], $b['equivalent_rate_per_sqm'], $b['contract_rate_consistent']]);
    }

    public function test_zero_rent_preserves_cusa_and_requires_both_exception_authorizations()
    {
        $request = $this->pending(['override_rate_per_sqm' => '0', 'reference_document' => 'Synthetic exemption']);
        $this->policy->update(['allow_out_of_range_overrides' => true, 'override_authority_reference' => 'Policy exception']);
        $this->invalid('override_authority', fn () => $this->approve($request));
        $this->policy->update(['allow_zero_rent_overrides' => true]);
        $this->approve($request);
        $basis = app(RentalCalculationService::class)->forAssignment($this->assignment, '2030-02');
        $this->assertSame(['0.00','300.00','300.00'], [$basis['monthly_rental'], $basis['monthly_cusa'], $basis['total_monthly_rental_cusa']]);
        $snapshot = app(RentalCalculationService::class)->assess($this->actor, $this->assignment, '2030-02', 'contract');
        $this->assertSame('300.00', app(RentalCalculationService::class)->fromAssessment($snapshot, '2030-03')['total_monthly_rental_cusa']);
    }

    public function test_dates_identity_amounts_authority_references_and_proposed_preview()
    {
        $s = $this->service();
        $a = $this->assignment;
        $preview = $s->preview($this->actor, $a, ['period' => '2030-02', 'override_type' => 'daily_amount', 'override_daily_amount' => '210.39']);
        $this->assertSame(['proposed', true, false, '210.39'], [$preview['computation_mode'], $preview['out_of_range'],
            $preview['contract_rate_consistent'], $preview['daily_rental']]);
        $this->assertDatabaseCount('rental_overrides', 0);
        foreach ([
            ['override_type', ['override_daily_amount' => '200']], ['override_rate_per_sqm', ['override_rate_per_sqm' => '-1']],
            ['override_rate_per_sqm', ['override_rate_per_sqm' => '1.001']], ['daily_rental', ['override_rate_per_sqm' => '999999999999.99']],
            ['business_id', ['business_id' => 9998]], ['reason', ['reason' => '  ']], ['effective_from', ['effective_from' => '2030-02-05']],
            ['effective_from', ['effective_from' => '2029-12-01']], ['effective_until', ['effective_until' => '2030-02-15']],
        ] as [$key, $changes]) {
            $this->invalid($key, fn () => $s->saveDraft($this->actor, $a, $this->data($changes)));
        }
        $a->update(['end_date' => '2030-02-28']);
        $this->invalid('effective_until', fn () => $this->pending());
        $p = $this->pending(['effective_until' => '2030-02-28', 'override_rate_per_sqm' => '30']);
        $this->policy->update(['allow_out_of_range_overrides' => true, 'override_authority_reference' => 'Test']);
        $this->invalid('override_authority', fn () => $this->approve($p));
        $a->tenant->update(['status' => 'inactive']);
        $this->invalid('stall_assignment_id', fn () => $this->approve($p));
    }

    public function test_draft_edit_submission_rejection_versioning_and_scope()
    {
        $s = $this->service();
        $data = $this->data();
        $r = $s->saveDraft($this->actor, $this->assignment, $data);
        $updated = $s->saveDraft($this->actor, $this->assignment, array_replace($data, ['reason' => 'Updated terms']), $r);
        $this->invalid('draft_fingerprint', fn () => $s->submit($this->actor, $r));
        $p = $s->submit($this->actor, $updated);
        $this->invalid('status', fn () => $s->saveDraft($this->actor, $this->assignment, $data, $p));
        $rejected = $s->reject($this->reviewer, $p, 'Incomplete evidence');
        $this->assertSame($rejected->id, $s->reject($this->reviewer, $p, 'Incomplete evidence')->id);
        $this->invalid('status', fn () => $s->reject($this->reviewer, $p, 'Other reason'));
        $this->assertSame('automatic', app(RentalCalculationService::class)->forAssignment($this->assignment, '2030-02')['computation_mode']);
        $next = $this->pending(['source_override_id' => $rejected->id]);
        $this->assertEquals(2, $next->revision);
        $this->assertEquals($rejected->id, $next->source_override_id);
        $this->assertSame('Updated terms', $rejected->fresh()->reason);
        $approved = $this->approve($next);
        $cancel = ['revocation_effective_from' => '2030-02-01', 'reason' => 'Synthetic cancellation'];
        $s->revoke($this->reviewer, $approved, $cancel);
        $this->assertSame('revoked', $s->revoke($this->reviewer, $approved, $cancel)->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rental_override.revised', 'entity_id' => $next->id]);
    }

    public function test_draft_and_posted_bills_keep_original_assessments_allocations_and_balances()
    {
        $billService = app(BillingService::class);
        $bill = $billService->generate($this->actor, $this->assignment, '2030-01', '2030-01-31');
        $frozen = $bill->assessment->getRawOriginal('snapshot');
        $request = $this->pending(['effective_from' => '2030-01-01']);
        $this->invalid('period', fn () => $this->approve($request));
        $posted = $billService->post($this->actor, $bill);
        $payments = app(\App\Services\PaymentService::class);
        $draft = $payments->createDraft($this->actor, ['tenant_id' => $bill->tenant_id, 'payment_date' => '2030-01-01',
            'amount' => '500.00', 'method' => 'cash', 'receipt_reference' => 'SYNTHETIC', 'idempotency_key' => (string) Str::uuid(),
            'allocations' => [$posted->id => '500.00']]);
        $paid = $payments->post($this->actor, $draft);
        $this->invalid('period', fn () => $this->approve($request));
        $override = $this->approve($this->pending());
        Carbon::setTestNow('2030-02-01');
        $manual = $billService->generate($this->actor, $this->assignment, '2030-02', '2030-02-28');
        $this->invalid('period', fn () => $this->service()->revoke($this->reviewer, $override, [
            'revocation_effective_from' => '2030-02-01', 'reason' => 'Synthetic correction']));
        $this->assertSame('7800.00', $manual->fresh()->amount);
        $this->assertSame('8364.00', $posted->fresh()->amount);
        $this->assertSame('7864.00', $posted->fresh()->balance());
        $this->assertSame('500.00', $paid->fresh()->amount);
        $this->assertEquals(500, $paid->allocations()->sum('amount'));
        $this->assertSame($frozen, $posted->fresh()->assessment->getRawOriginal('snapshot'));
    }

    public function test_approved_future_revision_covers_whole_period_without_replacing_frozen_ordinary_policy()
    {
        app(BusinessCategoryService::class)->retire($this->reviewer, $this->policy, ['effective_until' => '2030-01-31', 'reason' => 'Successor']);
        $future = $this->policy->replicate()->fill(['effective_from' => '2030-02-01', 'effective_until' => '2030-02-28',
            'status' => 'approved', 'daily_cusa' => '99.00', 'fixed_billing_days' => 10]);
        $future->save();
        $request = $this->pending(['effective_until' => '2030-02-28']);
        $approved = $this->approve($request);
        $this->assertEquals($future->id, $approved->rental_policy_id);
        $this->assertEquals($future->id, $approved->authority_snapshot['id']);
        $b = app(RentalCalculationService::class)->forAssignment($this->assignment, '2030-02');
        $this->assertEquals($this->policy->id, $b['rental_policy_id']);
        $this->assertSame('7800.00', $b['total_monthly_rental_cusa']);
        $this->assertSame('automatic', app(RentalCalculationService::class)->forAssignment($this->assignment, '2030-03')['computation_mode']);
    }

    public function test_renewal_does_not_inherit_rental_override()
    {
        $this->approve($this->pending(['effective_from' => '2030-01-01', 'effective_until' => '2030-01-31']));
        Carbon::setTestNow('2030-02-01');
        $renewed = app(AssignmentService::class)->transfer($this->actor, $this->assignment, ['stall_id' => $this->assignment->stall_id,
            'business_id' => $this->assignment->business_id, 'start_date' => '2030-02-01', 'reason' => 'Synthetic renewal', 'agreement_reference' => 'B']);
        $this->assertNotEquals($renewed->id, $this->assignment->id);
        $basis = app(RentalCalculationService::class)->forAssignment($renewed, '2030-02');
        $this->assertNull($basis['rental_override_id']);
        $this->assertSame('268.80', $basis['daily_rental']);
        $this->invalid('source_override_id', fn () => $this->service()->saveDraft($this->actor, $renewed,
            $this->data(['source_override_id' => RentalOverride::first()->id])));
    }

    public function test_view_only_user_and_inactive_requester_cannot_make_requests_directly()
    {
        $user = User::create(['name' => 'Viewer', 'email' => 'viewer@example.test', 'password' => 'unused', 'active' => true]);
        $user->assignRole('Treasury/Accounting Officer');
        $this->assertTrue($user->can('rentals.override.history'));
        foreach ([$user, $this->actor->fill(['active' => false])] as $denied) {
            try {
                $this->service()->saveDraft($denied, $this->assignment, $this->data());
                $this->fail('Unauthorized service invocation.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_ending_assignment_bounds_open_override_and_preserves_prior_coverage()
    {
        $override = $this->approve($this->pending(['effective_from' => '2030-01-01']));
        Carbon::setTestNow('2030-02-01');
        $a = app(AssignmentService::class)->transfer($this->actor, $this->assignment, ['stall_id' => $this->assignment->stall_id,
            'business_id' => $this->assignment->business_id, 'start_date' => '2030-02-01', 'reason' => 'Synthetic transfer', 'agreement_reference' => 'B']);
        $calc = app(RentalCalculationService::class);
        $this->assertSame('268.80', $calc->forAssignment($a, '2030-02')['daily_rental']);
        $this->invalid('period', fn () => $calc->forAssignment($this->assignment, '2030-02'));
        $this->assertSame('expired', $override->fresh()->displayStatus('2030-02-01'));
        $this->assertSame('250.00', $calc->forAssignment($this->assignment, '2030-01')['daily_rental']);
        app(BusinessCategoryService::class)->retire($this->reviewer, $this->policy, ['effective_until' => '2030-02-01', 'reason' => 'End synthetic schedule']);
        $this->assertSame('approved', $override->fresh()->status);
        $this->assertNull($override->fresh()->effective_until);
    }

    public function test_populated_forward_migration_preserves_original_assessment_bytes_and_adds_restricting_foreign_keys()
    {
        $db = \Illuminate\Support\Facades\DB::connection();
        $this->assertSame(':memory:', $db->getDatabaseName());
        $s = app(BillingService::class);
        $s->post($this->actor, $s->generate($this->actor, $this->assignment, '2030-01', '2030-01-31'));
        $tables = ['users','markets','market_sections','business_categories','business_category_rental_rates','rental_policies',
            'stalls','tenants','businesses','stall_assignments','rental_assessments','rental_agreements','rental_bills'];
        $connection = 'overrides_upgrade';
        config(['database.connections.'.$connection => ['driver' => 'sqlite','database' => ':memory:','prefix' => '','foreign_key_constraints' => true]]);
        \Illuminate\Support\Facades\DB::setDefaultConnection($connection);
        try {
            $last = '2026_11_19_000002_create_rental_overrides';
            $migrator = app('migrator');
            foreach ($migrator->getMigrationFiles([database_path('migrations')]) as $name => $path) {
                if ($name >= $last) {
                    break;
                }
                require_once $path;
                $migrator->resolve($name)->up();
            }
            $prior = [];
            foreach ($tables as $table) {
                $columns = array_flip(\Illuminate\Support\Facades\Schema::getColumnListing($table));
                foreach ($db->table($table)->orderBy('id')->get() as $row) {
                    $record = array_intersect_key((array) $row, $columns);
                    \Illuminate\Support\Facades\DB::table($table)->insert($record);
                    $prior[$table][$row->id] = $record;
                }
            }
            require_once database_path('migrations/'.$last.'.php');
            (new \CreateRentalOverrides())->up();
            foreach ($prior as $table => $rows) {
                foreach ($rows as $id => $before) {
                    $after = (array) \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->first();
                    $this->assertSame($before, array_intersect_key($after, $before), $table);
                }
            }
            $old = \App\Models\StallAssignment::findOrFail($this->assignment->id);
            $resolved = app(RentalCalculationService::class)->forAssignment($old, '2030-02');
            $this->assertSame('268.80', $resolved['automatic_daily_rental']);
            $this->assertSame('Fictional section', $resolved['market_section_name']);
            $this->assertNull($old->agreement->assessment->automatic_daily_rental);
            $this->assertFalse(RentalPolicy::first()->allow_out_of_range_overrides);
            $targets = array_column(\Illuminate\Support\Facades\DB::select('PRAGMA foreign_key_list(rental_assessments)'), 'table');
            $this->assertContains('rental_overrides', $targets);
            $this->assertContains('market_sections', $targets);
            $this->assertSame([], \Illuminate\Support\Facades\DB::select('PRAGMA foreign_key_check'));
        } finally {
            \Illuminate\Support\Facades\DB::setDefaultConnection($db->getName());
            \Illuminate\Support\Facades\DB::disconnect($connection);
        }
    }

    public function test_policy_exception_flags_need_a_displayed_reference_and_fresh_review()
    {
        $service = app(BusinessCategoryService::class);
        $draft = new RentalPolicy(['market_id' => $this->policy->market_id]);
        $data = ['daily_cusa' => '10.00', 'billing_day_rule' => 'fixed', 'fixed_billing_days' => 30, 'max_concurrent_assignments' => 1,
            'advance_deposit_authorized' => 0, 'allow_zero_rent_overrides' => 1, 'effective_from' => '2030-03-01', 'reference_document' => 'Synthetic revision'];
        $service->saveSchedule($this->actor, $draft, $data);
        $saved = RentalPolicy::latest('id')->firstOrFail();
        $fp = BusinessCategoryService::draftFingerprint($saved);
        $this->invalid('override_authority_reference', fn () => $service->approve($this->reviewer, $saved, $fp));
        $service->saveSchedule($this->actor, $saved, $data + ['override_authority_reference' => 'Synthetic exemption reference']);
        $this->invalid('draft_fingerprint', fn () => $service->approve($this->reviewer, $saved, $fp));
        $this->actingAs($this->reviewer)->get('/settings/rental-policies')->assertOk()
            ->assertSee('Zero-rent exemptions allowed')->assertSee('Synthetic exemption reference');
    }

    public function test_retirement_keeps_frozen_future_reference_month_after_assignment_ends()
    {
        $this->approve($this->pending());
        $assessment = app(RentalCalculationService::class)->assess($this->actor, $this->assignment, '2030-04', 'contract');
        app(AssignmentService::class)->end($this->actor, $this->assignment, '2030-01-01', 'Synthetic closure');
        $service = app(BusinessCategoryService::class);
        $this->invalid('effective_until', fn () => $service->retire($this->reviewer, $this->policy, ['effective_until' => '2030-04-29', 'reason' => 'Synthetic retirement']));
        $service->retire($this->reviewer, $this->policy, ['effective_until' => '2030-04-30', 'reason' => 'Coverage preserved']);
        $this->assertSame('250.00', $assessment->fresh()->daily_rental);
    }

    public function test_open_calendar_terms_reject_overflow_in_a_longer_covered_month()
    {
        $this->policy->update(['billing_day_rule' => 'calendar', 'fixed_billing_days' => null]);
        $renewed = app(AssignmentService::class)->transfer($this->actor, $this->assignment, ['stall_id' => $this->assignment->stall_id,
            'business_id' => $this->assignment->business_id, 'start_date' => '2030-01-01', 'reason' => 'Synthetic calendar renewal', 'agreement_reference' => 'C']);
        $data = $this->data(['override_type' => 'daily_amount', 'override_rate_per_sqm' => null, 'override_daily_amount' => '11000000000.00']);
        $this->invalid('total_initial_payment', fn () => $this->service()->saveDraft($this->actor, $renewed, $data));
        $feb = $this->service()->saveDraft($this->actor, $renewed, $data + ['effective_until' => '2030-02-28']);
        $this->assertSame('draft', $feb->status);
    }

    /** @dataProvider auditVisibility */
    public function test_override_audit_query_requires_both_permissions_across_outputs($permissions, $visible)
    {
        $reason = 'Sensitive fictional rental explanation';
        $this->pending(['reason' => $reason]);
        \App\Services\Audit::record($this->actor, 'synthetic.general', null, [], [], 'Visible system activity');
        \App\Services\Audit::record($this->actor, 'assessment.override_applied', $this->assignment->agreement->assessment);
        \App\Services\Audit::record($this->actor, 'synthetic.assignment', $this->assignment, [], [], 'Visible assignment activity');
        $user = User::create(['name' => 'Auditor', 'email' => 'auditor@example.test', 'password' => 'unused', 'active' => true]);
        $user->givePermissionTo(array_merge(['audit.view'], $permissions));
        $this->actingAs($user);
        foreach (['/audit', '/reports/audit-trail', '/reports/audit-trail?format=csv', '/reports/audit-trail?format=print'] as $url) {
            $r = $this->get($url)->assertOk();
            $content = strpos($url, 'format=csv') !== false ? $r->streamedContent() : $r->getContent();
            $this->assertStringContainsString('Visible system activity', $content);
            $this->assertStringContainsString('Visible assignment activity', $content);
            $this->assertSame($visible, strpos($content, $reason) !== false, $url);
            $this->assertSame($visible, strpos($content, 'assessment.override_applied') !== false, $url);
        }
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'RentalOverride', 'reason' => $reason]);
    }

    public function auditVisibility()
    {
        return [[[], false], [['rentals.override.view'], false], [['rentals.override.history'], false],
            [['rentals.override.view', 'rentals.override.history'], true]];
    }

    public function test_committed_draft_retries_after_date_and_assignment_change()
    {
        $data = $this->data(['effective_from' => '2030-01-01']);
        $s = $this->service();
        $draft = $s->saveDraft($this->actor, $this->assignment, $data);
        Carbon::setTestNow('2030-01-02');
        $this->assertSame($draft->id, $s->saveDraft($this->actor, $this->assignment, $data)->id);
        app(AssignmentService::class)->end($this->actor, $this->assignment, '2030-01-02', 'Synthetic end');
        $this->assertSame($draft->id, $s->saveDraft($this->actor, $this->assignment, $data)->id);
        $this->invalid('submission_key', fn () => $s->saveDraft($this->actor, $this->assignment, array_replace($data, ['reason' => 'Changed'])));
        $this->invalid('submission_key', fn () => $s->saveDraft($this->reviewer, $this->assignment, $data));
        $this->invalid('tenant_id', fn () => $s->saveDraft($this->actor, $this->assignment, $data + ['tenant_id' => 999]));
        $this->assertDatabaseCount('rental_overrides', 1);
    }

    public function test_blank_end_is_open_and_retries_canonically()
    {
        $data = $this->data(['effective_until' => '']);
        $draft = $this->service()->saveDraft($this->actor, $this->assignment, $data);
        $this->assertNull($draft->effective_until);
        $this->assertSame($draft->id, $this->service()->saveDraft($this->actor, $this->assignment, array_replace($data, ['effective_until' => null]))->id);
        $this->approve($this->service()->submit($this->actor, $draft));
        $this->assertSame('250.00', app(RentalCalculationService::class)->forAssignment($this->assignment, '2030-03')['daily_rental']);
    }

    /** @dataProvider peerPurposes */
    public function test_cross_purpose_checks_financial_basis_before_writing($first, $second)
    {
        $calc = app(RentalCalculationService::class);
        $peer = $calc->assess($this->actor, $this->assignment, '2030-01', $first);
        // Simulate an imported historical snapshot with conflicting financial terms.
        \Illuminate\Support\Facades\DB::table('rental_assessments')->where('id', $peer->id)->update(['monthly_cusa' => '90.00']);
        $before = $peer->fresh()->getRawOriginal();
        $this->invalid('assessment', fn () => $calc->assess($this->actor, $this->assignment, '2030-01', $second));
        $this->assertSame($before, $calc->assess($this->actor, $this->assignment, '2030-01', $first)->getRawOriginal());
        $this->assertDatabaseCount('rental_assessments', 2);
    }

    public function peerPurposes()
    {
        return [['billing','contract'], ['contract','billing']];
    }

    public function test_cross_purpose_accepts_matching_legacy_automatic_snapshot()
    {
        $calc = app(RentalCalculationService::class);
        $peer = $calc->assess($this->actor, $this->assignment, '2030-01', 'billing');
        \Illuminate\Support\Facades\DB::table('rental_assessments')->where('id', $peer->id)->update([
            'automatic_daily_rental' => null, 'automatic_rate_per_sqm' => null, 'market_section_id' => null,
            'market_section_name' => null, 'basis' => json_encode($this->assignment->agreement->assessment->basis),
        ]);
        $second = $calc->assess($this->actor, $this->assignment, '2030-01', 'contract');
        $this->assertSame('268.80', $second->daily_rental);
        $this->assertSame('268.80', $second->automatic_daily_rental);
        $this->assertNull($peer->fresh()->automatic_daily_rental);
    }

    public function test_cross_purpose_checks_override_identity_even_when_rent_matches()
    {
        $row = $this->approve($this->pending(['effective_from' => '2030-01-01', 'override_rate_per_sqm' => '26.88']));
        $calc = app(RentalCalculationService::class);
        $peer = $calc->assess($this->actor, $this->assignment, '2030-01', 'contract');
        $this->assertEquals($row->id, $peer->rental_override_id);
        \Illuminate\Support\Facades\DB::table('rental_assessments')->where('id', $peer->id)->update(['rental_override_id' => null]);
        $this->invalid('assessment', fn () => $calc->assess($this->actor, $this->assignment, '2030-01', 'billing'));
    }

    public function test_cross_purpose_accepts_same_approval_after_future_revocation()
    {
        $override = $this->approve($this->pending(['effective_from' => '2030-01-01']));
        $calc = app(RentalCalculationService::class);
        $lease = $calc->assess($this->actor, $this->assignment, '2030-01', 'contract');
        $this->service()->revoke($this->reviewer, $override, ['revocation_effective_from' => '2030-02-01', 'reason' => 'Future return']);
        $bill = $calc->assess($this->actor, $this->assignment, '2030-01', 'billing');
        $this->assertSame($lease->rental_override_id, $bill->rental_override_id);
        $this->assertSame('7800.00', $bill->total_monthly_rental_cusa);
        $this->assertNull($lease->fresh()->basis['override']['revocation_effective_from']);
    }

    public function test_whole_policy_period_overlap_cancellation_and_retirement()
    {
        $req = $this->pending();
        $this->policy->update(['effective_until' => '2030-12-31']);
        $this->invalid('rental_policy', fn () => $this->approve($req));
        $this->policy->update(['effective_until' => null]);
        $approved = $this->approve($req);
        $other = $this->pending(['effective_from' => '2030-03-01']);
        $this->invalid('effective_from', fn () => $this->approve($other));
        $retire = fn () => app(BusinessCategoryService::class)->retire($this->reviewer, $this->policy, [
            'effective_until' => '2030-02-28', 'reason' => 'Replace synthetic schedule']);
        $this->invalid('effective_until', $retire);
        $this->service()->revoke($this->reviewer, $approved, ['revocation_effective_from' => '2030-02-01', 'reason' => 'Cancel before activation']);
        $this->approve($other);
        $this->invalid('effective_until', $retire);
        $this->service()->revoke($this->reviewer, $other, ['revocation_effective_from' => '2030-03-01', 'reason' => 'Cancel future approval']);
        $retire();
        $this->assertSame('retired', $this->policy->fresh()->status);
    }
}
