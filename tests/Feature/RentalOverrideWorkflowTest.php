<?php

namespace Tests\Feature;

use App\Models\{BusinessCategoryRentalRate, Market, MarketSection, RentalOverride, RentalPolicy, Stall, Tenant, User};
use App\Services\{AssignmentService, RentalOverrideService};
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Fixtures\ApprovedRentalConfiguration;
use Tests\TestCase;

class RentalOverrideWorkflowTest extends TestCase
{
    use ApprovedRentalConfiguration, RefreshDatabase;

    private $requester;
    private $reviewer;
    private $tenant;
    private $stall;
    private $section;
    private $assignment;
    private $url;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2030-01-01');
        $this->seed(RoleSeeder::class);
        $this->requester = $this->user('Requester', 'Market Staff');
        $this->reviewer = $this->user('Reviewer', 'Super Administrator');
        $this->actingAs($this->requester);
        $this->tenant = Tenant::create(['name'=>'Synthetic tenant', 'reference'=>'T1', 'status'=>'active']);
        $market = Market::create(['name'=>'Fictional market', 'code'=>'M1', 'status'=>'active']);
        $this->section = MarketSection::create(['market_id'=>$market->id, 'code'=>'S1', 'name'=>'Original section', 'status'=>'active']);
        $this->stall = Stall::create(['market_id'=>$market->id, 'market_section_id'=>$this->section->id,
            'code'=>'A1', 'classification'=>'standard', 'location_type'=>'outer', 'area'=>'10.00', 'status'=>'vacant']);
        $business = $this->businessWithApprovedRental($this->reviewer, $this->tenant, $market->id);
        BusinessCategoryRentalRate::where('business_category_id', $business->business_category_id)->update(['minimum_rate'=>'21.04']);
        $this->assignment = app(AssignmentService::class)->assign($this->reviewer, [
            'tenant_id'=>$this->tenant->id, 'stall_id'=>$this->stall->id, 'business_id'=>$business->id,
            'start_date'=>'2029-12-01', 'agreement_reference'=>'REF-TEST',
        ]);
        $this->url = "/tenants/{$this->tenant->id}/assignments/{$this->assignment->id}/rental-overrides";
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_preview_and_direct_actions_are_scoped_and_permission_gated(): void
    {
        $data = $this->terms() + ['period'=>'2030-02', 'proposed_daily_rental'=>'3.00'];
        $this->postJson($this->url.'/preview', $data)->assertOk()->assertJsonPath('computation_mode', 'proposed')
            ->assertJsonPath('daily_rental', '225.00')->assertJsonPath('automatic_daily_rental', '250.00')
            ->assertJsonPath('rental_override_id', null);
        $this->postJson($this->url.'/preview', $data + ['override_daily_amount'=>'150'])
            ->assertStatus(422)->assertJsonValidationErrors('override_type');
        $this->postJson($this->url.'/preview', $data + ['stall_id'=>999])->assertStatus(422)->assertJsonValidationErrors('stall_id');
        $this->assertDatabaseCount('rental_overrides', 0);
        $other = Tenant::create(['name'=>'Wrong tenant', 'reference'=>'T2', 'status'=>'active']);
        $foreignUrl = "/tenants/{$other->id}/assignments/{$this->assignment->id}/rental-overrides";
        $this->postJson($foreignUrl.'/preview', $data)->assertNotFound();
        $this->postJson($foreignUrl, $data)->assertNotFound();
        $this->get($foreignUrl)->assertNotFound();
        $pending = $this->pending();
        $second = $this->assignment->replicate();
        $second->active_stall_id = null;
        $second->status = 'ended';
        $second->save();
        $wrongAssignment = "/tenants/{$this->tenant->id}/assignments/{$second->id}/rental-overrides/{$pending->id}";
        $this->actingAs($this->reviewer)->postJson($wrongAssignment.'/approve', ['fingerprint'=>str_repeat('a', 64)])->assertNotFound();
        $viewer = $this->user('Treasurer', 'Treasury/Accounting Officer');
        $this->actingAs($viewer)->get($this->url)->assertOk()->assertSee($pending->reason)->assertDontSee('Approve request');
        $this->postJson($this->url.'/preview', $data)->assertForbidden();
        $this->postJson($this->url, $data)->assertForbidden();
        foreach (['submit', 'approve', 'reject', 'revoke'] as $action) {
            $this->postJson($this->url.'/'.$pending->id.'/'.$action)->assertForbidden();
        }
    }

    public function test_store_submit_intent_is_atomic_and_exact_replay_is_idempotent(): void
    {
        $terms = $this->terms(['intent'=>'submit', 'period'=>'2030-02']);
        $this->get($this->url.'/create?period=2030-02')->assertOk()->assertSee('Preview proposed rent')
            ->assertSee('name="intent" value="submit"', false);
        $this->post($this->url, $terms)->assertRedirect($this->url.'?period=2030-02');
        $pending = RentalOverride::sole();
        $this->assertSame('pending', $pending->status);
        $this->post($this->url, $terms)->assertRedirect($this->url.'?period=2030-02');
        $this->postJson($this->url, array_replace($terms, ['reason'=>'Changed replay']))->assertStatus(422)->assertJsonValidationErrors('submission_key');
        $this->assertDatabaseCount('rental_overrides', 1);
    }

    public function test_finite_assignment_form_keeps_reference_month_and_offers_only_full_months(): void
    {
        $this->assignment->update(['end_date'=>'2030-04-10']);
        $this->get($this->url.'/create?period=2030-02')->assertOk()
            ->assertSee('name="period" value="2030-02"', false)
            ->assertSee('type="date" value="2030-02-01"', false)
            ->assertSee('type="date" value="2030-03-31"', false);
        $this->assignment->update(['end_date'=>'2030-01-25']);
        $this->get($this->url.'/create?period=2030-02')
            ->assertRedirect($this->url.'?period=2030-02')->assertSessionHasErrors('assignment');
    }

    public function test_rental_mode_selection_opens_request_or_prospective_return_workflow(): void
    {
        $profile = '/manage/tenants/'.$this->tenant->id.'?tab=rental&period=2030-02';
        $create = $this->url.'/create?period=2030-02&computation_mode=';
        $this->get($profile)->assertOk()->assertSee('name="computation_mode"', false)
            ->assertSee('Automatic (default)')->assertSee('Manual override');
        $this->get($create.'automatic')->assertRedirect($profile);
        $this->get($create.'manual_override')->assertOk()->assertSee('Override method (required)');
        $this->assertDatabaseCount('rental_overrides', 0);

        $pending = $this->pending();
        app(RentalOverrideService::class)->approve($this->reviewer, $pending, RentalOverrideService::fingerprint($pending));
        $this->actingAs($this->reviewer)->get($create.'automatic')->assertRedirect($this->url.'?period=2030-02');
        $this->get($this->url.'?period=2030-02')->assertOk()->assertSee('Return to automatic');
        $this->assertSame('approved', $pending->fresh()->status);
    }

    public function test_draft_edit_and_submit_bind_displayed_terms(): void
    {
        $terms = $this->terms();
        $this->post($this->url, $terms + ['intent'=>'save'])->assertRedirect();
        $draft = RentalOverride::sole();
        $url = $this->url.'/'.$draft->id;
        $stale = RentalOverrideService::fingerprint($draft);
        $this->get($url.'/edit')->assertOk()->assertSee($stale)->assertSee($draft->request_hash);
        $this->putJson($url, array_replace($terms, ['reason'=>'Revised pending request']))
            ->assertStatus(422)->assertJsonValidationErrors('draft_fingerprint');
        $this->put($url, array_replace($terms, ['reason'=>'Revised pending request', 'draft_fingerprint'=>$stale]))->assertRedirect();
        $this->putJson($url, array_replace($terms, ['reason'=>'Stale browser edit', 'draft_fingerprint'=>$stale]))
            ->assertStatus(422)->assertJsonValidationErrors('draft_fingerprint');
        $this->assertSame('Revised pending request', $draft->fresh()->reason);
        $this->postJson($url.'/submit', ['draft_fingerprint'=>$stale, 'draft_terms_hash'=>$draft->request_hash])
            ->assertStatus(422)->assertJsonValidationErrors('draft_fingerprint');
        $this->assertSame('draft', $draft->fresh()->status);
        $fresh = $draft->fresh();
        $submission = ['draft_fingerprint'=>RentalOverrideService::fingerprint($fresh), 'draft_terms_hash'=>$fresh->request_hash];
        $this->post($url.'/submit', $submission)->assertRedirect();
        RentalPolicy::firstOrFail()->update(['reference_document'=>'DRIFTED TEST AUTHORITY']);
        $this->post($url.'/submit', $submission)->assertRedirect();
        $this->assertSame('pending', $draft->fresh()->status);
    }

    public function test_stale_html_edit_retry_shows_and_saves_the_current_draft(): void
    {
        $terms = $this->terms(['reason'=>'Original draft terms']);
        $service = app(RentalOverrideService::class);
        $draft = $service->saveDraft($this->requester, $this->assignment, $terms);
        $url = $this->url.'/'.$draft->id;
        $edit = $url.'/edit?period=2030-02';
        $oldFingerprint = RentalOverrideService::fingerprint($draft);
        $service->saveDraft($this->requester, $this->assignment, array_replace($terms, ['reason'=>'Newer saved terms']), $draft);

        $this->from($edit)->put($url, array_replace($terms, [
            'reason'=>'Stale browser edits', 'draft_fingerprint'=>$oldFingerprint, 'period'=>'2030-02',
        ]))->assertRedirect($edit)->assertSessionHasErrors('draft_fingerprint');
        $response = $this->get($edit)->assertOk()
            ->assertSee('Showing the latest saved draft')
            ->assertSee('Newer saved terms')->assertDontSee('Stale browser edits');
        $this->assertSame(1, preg_match('/name="draft_fingerprint" value="([a-f0-9]{64})"/', $response->getContent(), $fingerprint));
        $this->assertSame(1, preg_match('/<textarea id="override_reason"[^>]*>(.*?)<\/textarea>/s', $response->getContent(), $reason));
        $this->put($url, array_replace($terms, [
            'reason'=>html_entity_decode($reason[1]), 'draft_fingerprint'=>$fingerprint[1], 'period'=>'2030-02',
        ]))->assertRedirect($edit)->assertSessionHasNoErrors();
        $this->assertSame('Newer saved terms', $draft->fresh()->reason);
    }

    public function test_two_tabs_bind_pending_replay_to_the_original_displayed_terms(): void
    {
        $terms = $this->terms(['reason'=>'Tab A terms']);
        $service = app(RentalOverrideService::class);
        $draft = $service->saveDraft($this->requester, $this->assignment, $terms);
        $url = $this->url.'/'.$draft->id.'/submit';
        $tabA = ['draft_terms_hash'=>$draft->request_hash, 'draft_fingerprint'=>RentalOverrideService::fingerprint($draft)];
        $updated = $service->saveDraft($this->requester, $this->assignment, array_replace($terms, ['reason'=>'Tab B terms']), $draft);
        $tabB = ['draft_terms_hash'=>$updated->request_hash, 'draft_fingerprint'=>RentalOverrideService::fingerprint($updated)];
        $this->post($url, $tabB)->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson($url, $tabA)->assertStatus(422)->assertJsonValidationErrors('draft_fingerprint');
        RentalPolicy::firstOrFail()->update(['reference_document'=>'Authority changed after submission']);
        $this->post($url, $tabB)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $draft->fresh()->status);
        $this->assertSame('Tab B terms', $draft->fresh()->reason);
    }

    public function test_request_only_reader_cannot_distinguish_existing_and_missing_revision_sources(): void
    {
        $source = $this->pending();
        $limited = $this->user('LimitedRequester', 'Authorized Collection Officer');
        $limited->givePermissionTo(['rentals.override.view', 'rentals.override.request']);
        $this->actingAs($limited)->get($this->url.'/create')->assertOk();
        $this->get($this->url.'/create?source_override_id='.$source->id)->assertForbidden();
        $this->get($this->url.'/create?source_override_id='.($source->id + 999))->assertForbidden();
    }

    public function test_approval_only_reader_sees_request_specific_range_and_exemption_warnings(): void
    {
        $outside = $this->pending(['override_type'=>'daily_amount', 'override_rate_per_sqm'=>null,
            'override_daily_amount'=>'200', 'reason'=>'Direct daily outside approved range']);
        $this->pending(['override_type'=>'daily_amount', 'override_rate_per_sqm'=>null,
            'override_daily_amount'=>'0', 'reason'=>'Zero rent exemption requested']);
        $approver = $this->user('LimitedApprover', 'Authorized Collection Officer');
        $approver->givePermissionTo(['rentals.override.view', 'rentals.override.history', 'rentals.override.approve']);
        $this->assertFalse($approver->can('rentals.override.request'));
        $response = $this->actingAs($approver)->get($this->url)->assertOk()
            ->assertSee('Proposed daily rent is outside the approved category range.')
            ->assertSee('This proposal requests a zero-rent exemption. CUSA remains payable.')
            ->assertSee('Approve request')->assertSee(RentalOverrideService::fingerprint($outside));
        $this->assertSame(2, substr_count($response->getContent(), 'Proposed daily rent is outside the approved category range.'));
        $this->assertSame(1, substr_count($response->getContent(), 'This proposal requests a zero-rent exemption.'));
    }

    public function test_approval_binds_policy_and_rejection_revision_and_revocation_retain_history(): void
    {
        $pending = $this->pending();
        $this->requester->givePermissionTo('rentals.override.approve');
        $url = $this->url.'/'.$pending->id;
        $review = RentalOverrideService::review($pending);
        $this->postJson($url.'/approve', ['fingerprint'=>$review['fingerprint']])->assertStatus(422)->assertJsonValidationErrors('approved_by');
        $this->actingAs($this->reviewer)->get($this->url.'?period=2030-02')
            ->assertOk()->assertSee($review['fingerprint'])->assertSee($review['policy']['reference_document'])
            ->assertSee('Proposed daily rent')->assertSee('Pending');
        RentalPolicy::firstOrFail()->update(['reference_document'=>'CHANGED-TEST-AUTHORITY']);
        $this->postJson($url.'/approve', ['fingerprint'=>$review['fingerprint']])->assertStatus(422)->assertJsonValidationErrors('fingerprint');
        $this->post($url.'/reject', ['reason'=>'Required revision'])->assertRedirect();
        $this->actingAs($this->requester)->get($this->url.'/create?source_override_id='.$pending->id)->assertOk()
            ->assertSee('name="source_override_id" value="'.$pending->id.'"', false);
        $this->post($this->url, $this->terms(['source_override_id'=>$pending->id, 'intent'=>'submit']))->assertRedirect();
        $new = RentalOverride::where('source_override_id', $pending->id)->sole();
        $this->actingAs($this->reviewer);
        $newUrl = $this->url.'/'.$new->id;
        $fp = RentalOverrideService::review($new)['fingerprint'];
        $this->post($newUrl.'/approve', ['fingerprint'=>$fp])->assertRedirect();
        $this->post($newUrl.'/approve', ['fingerprint'=>$fp])->assertRedirect();
        $this->post($newUrl.'/revoke', ['revocation_effective_from'=>'2030-03-01', 'reason'=>'Return decision'])->assertRedirect();
        $this->post($newUrl.'/revoke', ['revocation_effective_from'=>'2030-03-01', 'reason'=>'Return decision'])->assertRedirect();
        $this->postJson($newUrl.'/revoke', ['revocation_effective_from'=>'2030-03-01', 'reason'=>'Conflicting return'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        RentalPolicy::firstOrFail()->update(['reference_document'=>'LATER-TEST-AUTHORITY']);
        $this->get($this->url.'?period=2030-03')->assertOk()->assertSee('Required revision')->assertSee('Return decision')
            ->assertSee('CHANGED-TEST-AUTHORITY')->assertDontSee('LATER-TEST-AUTHORITY')->assertSee('Revoked');
    }

    public function test_map_and_tenant_show_reference_month_and_mask_override_information(): void
    {
        $pending = $this->pending();
        app(RentalOverrideService::class)->approve($this->reviewer, $pending, RentalOverrideService::fingerprint($pending));
        $profile = '/manage/tenants/'.$this->tenant->id.'?tab=rental';
        $map = '/map/stalls/'.$this->stall->id;
        $this->section->update(['name'=>'Renamed section']);
        $this->get($profile)->assertOk()->assertSee('Applicable daily rent')->assertSee('250.00')->assertDontSee('Approved override applies');
        $this->get($profile.'&period=2030-02')->assertOk()->assertSee('Approved override applies')->assertSee('225.00');
        $this->get($map.'?period=2030-02')->assertOk()->assertSee('Approved override applies')->assertSee('Renamed section')
            ->assertSee('Original section')->assertSee('225.00');
        $this->get('/map?period=2030-02&computation_mode=manual_override')->assertOk()->assertSee('?period=2030-02');
        $this->get('/map?period=2030-02&computation_mode=automatic')->assertOk()->assertDontSee('?period=2030-02');
        Carbon::setTestNow('2030-02-01');
        $stallDetail = '/manage/stalls/'.$this->stall->id;
        $this->get($stallDetail)->assertOk()->assertSee('Initial assessed daily rate per sq. m. (PHP)')
            ->assertSee('Initial assessed daily base rent (PHP)')->assertSee('250.00')->assertSee($profile);
        $this->get('/manage/stalls')->assertOk()->assertSee('Initial assessment / legacy rent');
        $this->get('/manage/tenants')->assertOk()->assertSee('Initial assessment · 2029-12');
        $viewer = $this->user('Viewer', 'LGU Management Viewer');
        $this->actingAs($viewer)->get($map)->assertOk()->assertSee('Renamed section')
            ->assertDontSee('Approved override')->assertDontSee('225.00')->assertDontSee('250.00')->assertDontSee($pending->reason);
        $this->get($stallDetail)->assertOk()->assertDontSee('Initial assessed daily base rent')->assertDontSee($profile);
        $this->get('/reports/rental-overrides')->assertForbidden();
        $this->get('/map?computation_mode=manual_override')->assertForbidden();
        $this->assignment->update(['status'=>'ended', 'end_date'=>'2030-02-28']);
        $this->actingAs($this->requester)->get($profile.'&period=2030-04')->assertOk()->assertSee('Initial assessment')
            ->assertSee('Override history')->assertDontSee('Request manual override');
        $this->get($this->url.'?period=2030-04')->assertOk()->assertSee($pending->reason)->assertSee('Expired');
    }

    public function test_report_filters_are_shared_by_screen_and_export(): void
    {
        $approved = $this->pending(['reason'=>'Approved fixture', 'effective_until'=>'2030-02-28']);
        app(RentalOverrideService::class)->approve($this->reviewer, $approved, RentalOverrideService::fingerprint($approved));
        $this->pending(['reason'=>'=Pending fixture', 'effective_from'=>'2030-03-01']);
        $this->actingAs($this->reviewer);
        $url = '/reports/rental-overrides?status=pending&as_of=2030-03-01&section_id='.$this->section->id;
        $this->get($url)->assertOk()->assertSee('=Pending fixture')->assertDontSee('Approved fixture');
        $this->get($url.'&format=print')->assertOk()->assertSee('=Pending fixture')->assertDontSee('Approved fixture');
        $csv = $this->get($url.'&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=Pending fixture", $csv);
        $this->assertStringNotContainsString('Approved fixture', $csv);
        $xlsx = $this->get($url.'&format=xlsx')->assertOk()->streamedContent();
        $file = tempnam(sys_get_temp_dir(), 'override-report');
        try {
            file_put_contents($file, $xlsx);
            $book = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file);
            $cells = json_encode($book->getActiveSheet()->toArray());
            $this->assertStringContainsString('=Pending fixture', $cells);
            $this->assertStringNotContainsString('Approved fixture', $cells);
            $book->disconnectWorksheets();
        } finally {
            unlink($file);
        }
        $pdfReasons = null;
        \Illuminate\Support\Facades\View::composer('reports.print', function ($view) use (&$pdfReasons) {
            $pdfReasons = $view->getData()['rows']->pluck('reason')->all();
        });
        $pdf = $this->get($url.'&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertSame(['=Pending fixture'], $pdfReasons);
        $this->get('/reports/rental-overrides?status=expired&as_of=2030-03-01')
            ->assertOk()->assertSee('Approved fixture')->assertDontSee('=Pending fixture');
        $this->get('/reports/rental-overrides?computation_mode=manual_override&as_of=2030-02-15')
            ->assertOk()->assertSee('Approved fixture')->assertDontSee('=Pending fixture')
            ->assertViewHas('rows', function ($rows) {
                $row = $rows->first();
                return $rows->total() === 1 && $row->computation_mode === 'manual_override' &&
                    $row->applicable_daily_rent == 225.00 && $row->applicable_daily_difference == -25.00;
            });
        $this->get('/reports/rental-assessments?computation_mode=manual_override')
            ->assertOk()->assertSee('No matching records.');
        $this->get('/reports/stall-master')->assertOk()->assertDontSee('name="computation_mode"', false);
    }

    public function test_pending_profile_state_and_assessment_mode_respect_override_visibility(): void
    {
        $pending = $this->pending(['effective_until'=>'2030-02-28']);
        $profile = '/manage/tenants/'.$this->tenant->id.'?tab=rental&period=2030-02';
        $this->get($profile)->assertOk()->assertSee('1 pending request')->assertDontSee('Approved override applies');

        $service = app(RentalOverrideService::class);
        $service->approve($this->reviewer, $pending, RentalOverrideService::fingerprint($pending));
        Carbon::setTestNow('2030-02-01');
        app(\App\Services\RentalCalculationService::class)->assess($this->reviewer, $this->assignment, '2030-02', 'billing');
        Carbon::setTestNow('2030-04-01');
        $report = '/reports/rental-assessments?as_of=2030-04-01';
        $this->actingAs($this->reviewer)->get($report.'&computation_mode=manual_override')->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1 && $rows->first()->period === '2030-02' &&
                $rows->first()->computation_mode === 'manual_override');
        $this->actingAs($this->user('Collector', 'Authorized Collection Officer'))->get($report)->assertOk()
            ->assertDontSee('name="computation_mode"', false)->assertDontSee('manual_override')->assertSee('225');
        $this->get($report.'&computation_mode=manual_override')->assertForbidden();
    }

    public function test_report_forms_render_only_supported_scope_and_date_filters(): void
    {
        $this->actingAs($this->reviewer)->get('/reports/stall-master')->assertOk()
            ->assertSee('name="section_id"', false)->assertDontSee('name="from"', false)
            ->assertDontSee('name="tenant_id"', false)->assertDontSee('name="recorded_by"', false);
        $this->get('/reports/audit-trail')->assertOk()
            ->assertSee('name="from"', false)->assertDontSee('name="stall_id"', false);
        $this->get('/reports/rental-overrides')->assertOk()
            ->assertSee('name="as_of"', false)->assertSee('name="section_id"', false)->assertDontSee('name="from"', false)
            ->assertDontSee('name="recorded_by"', false);
    }

    private function user(string $name, string $role): User
    {
        $user = User::create(['name'=>$name, 'email'=>strtolower($name).'@example.test', 'password'=>'unused', 'active'=>true]);
        return $user->assignRole($role);
    }

    private function terms(array $changes = []): array
    {
        return array_replace(['submission_key'=>(string) Str::uuid(), 'override_type'=>'rate_per_sqm',
            'override_rate_per_sqm'=>'22.50', 'effective_from'=>'2030-02-01', 'reason'=>'Synthetic override reason'], $changes);
    }

    private function pending(array $changes = []): RentalOverride
    {
        $service = app(RentalOverrideService::class);
        return $service->submit($this->requester, $service->saveDraft($this->requester, $this->assignment, $this->terms($changes)));
    }
}
