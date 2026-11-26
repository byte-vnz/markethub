<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Fixtures\ApprovedRentalConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\{
    User,
    Market,
    MarketSection,
    Stall,
    Tenant,
    StallAssignment,
    RentalRate,
    RentalAgreement,
    ContractTemplate,
    LeaseContract,
};
use App\Services\{AssignmentService, LeaseContractService, ContractTemplateService, TenantDataService};
use Database\Seeders\RoleSeeder;
class LeaseContractWorkflowTest extends TestCase
{
    use RefreshDatabase, ApprovedRentalConfiguration;

    public function test_terminate_button_names_the_action_it_submits(): void
    {
        $contract = app(LeaseContractService::class)->create($this->admin, $this->input());
        $html = $this->actingAs($this->admin)->get(route('contracts.show', $contract))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<button[^>]*data-confirm="Terminate this contract and retain its history\?"[^>]*formaction="[^\"]+\/terminate"[^>]*>Terminate contract<\/button>/',
            $html,
        );
    }
    private $admin;
    private $assignment;
    private $template;
    private $folder;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::create([
            "name" => "Synthetic reviewer",
            "email" => "lease@example.test",
            "password" => Hash::make(Str::random(40)),
            "active" => true,
        ]);
        $this->admin->assignRole("Super Administrator");
        $m = Market::create(["code" => "TEST", "name" => "Synthetic market", "status" => "active"]);
        $s = MarketSection::create([
            "market_id" => $m->id,
            "code" => "A",
            "name" => "Section",
            "status" => "active",
        ]);
        $stall = Stall::create([
            "market_id" => $m->id,
            "market_section_id" => $s->id,
            "code" => "A-001",
            "classification" => "test",
            "area" => "10.00",
            "location_type" => "inner",
            "status" => "vacant",
        ]);
        $t = Tenant::create([
            "reference" => "T-SYNTHETIC",
            "name" => "SYNTHETIC, PERSON, A.",
            "last_name" => "Synthetic",
            "first_name" => "Person",
            "middle_initial" => "A.",
            "civil_status" => "Single",
            "gender" => "Female",
            "citizenship" => "Filipino",
            "address" => "Fictional test address",
            "phone" => "09000000000",
            "government_id_type" => "Synthetic ID",
            "government_id_number" => "TEST-PRIVATE-ID",
            "government_id_date_issued" => "2025-01-01",
            "government_id_place_issued" => "Synthetic City",
            "status" => "active",
        ]);
        $business = $this->businessWithApprovedRental($this->admin, $t, $m->id);
        $this->assignment = app(AssignmentService::class)->assign($this->admin, [
            "tenant_id" => $t->id,
            "stall_id" => $stall->id,
            "business_id" => $business->id,
            "start_date" => "2020-01-01",
            "agreement_reference" => "SYNTHETIC-LEASE-AGREEMENT",
        ]);
        $this->folder = "private/contract-testing/" . Str::uuid();
        Storage::makeDirectory($this->folder);
        $path = $this->folder . "/template.docx";
        $source = storage_path(
            "app/private/references/v04_San_Pedro_Public_Market_Lease_Template.docx",
        );
        app(ContractTemplateService::class)->prepare($source, storage_path("app/" . $path));
        $this->template = ContractTemplate::create([
            "name" => "Synthetic template review",
            "version" => (string) Str::uuid(),
            "path" => $path,
            "source_checksum" => hash_file("sha256", $source),
            "checksum" => hash_file("sha256", storage_path("app/" . $path)),
            "approved" => true,
            "effective_date" => "2020-01-01",
            "created_by" => $this->admin->id,
            "approved_by" => $this->admin->id,
        ]);
        config([
            "lease.numbering_approval" => "SYNTHETIC-NUMBER-POLICY",
            "lease.rental_policy_approval" => "SYNTHETIC-RENTAL-POLICY",
            "contracts.pdf_executable" => null,
        ]);
    }
    protected function tearDown(): void
    {
        if ($this->app) {
            foreach (LeaseContract::all() as $c) {
                if ($c->docx_path) {
                    Storage::deleteDirectory(dirname($c->docx_path));
                }
                if ($c->signed_path) {
                    Storage::delete($c->signed_path);
                }
            }
            Storage::deleteDirectory($this->folder);
        }
        parent::tearDown();
    }
    private function input()
    {
        return [
            "stall_assignment_id" => $this->assignment->id,
            "contract_template_id" => $this->template->id,
            "start_date" => today()->startOfMonth()->toDateString(),
            "end_date" => today()->addMonths(3)->toDateString(),
            "execution_date" => today()->toDateString(),
            "submission_key" => (string) Str::uuid(),
        ];
    }
    public function test_optional_assignment_goods_do_not_bypass_contract_requirements()
    {
        $this->assignment->business->update(['permitted_goods'=>null]);
        $this->assignment = app(AssignmentService::class)->transfer($this->admin, $this->assignment, [
            'stall_id'=>$this->assignment->stall_id,'business_id'=>$this->assignment->business_id,
            'start_date'=>today()->startOfMonth()->toDateString(),'reason'=>'Fictional renewal with optional goods',
        ]);
        $this->assertNull($this->assignment->agreement->assessment->snapshot['business']['permitted_goods']);
        try {
            app(LeaseContractService::class)->create($this->admin,$this->input());
            $this->fail('An assignment without verified goods cannot create a legal contract.');
        } catch (ValidationException $exception) {
            $this->assertSame('Verify permitted goods of 1 to 180 characters and renew the assignment before creating a contract.',
                $exception->errors()['contract'][0]);
        }
        $this->assertSame(0,LeaseContract::count());
    }

    public function test_v04_generates_from_a_private_approved_copy_and_retains_v02_history()
    {
        $source = config('contracts.source_path');
        $this->assertSame('v04_San_Pedro_Public_Market_Lease_Template.docx', basename($source));
        $originalHash = hash_file('sha256', $source);
        $path = $this->folder.'/v04-working.docx';
        $meta = app(ContractTemplateService::class)->prepare($source, storage_path('app/'.$path));
        $this->assertSame(ContractTemplateService::V04_SHA256, $meta['source_checksum']);
        $this->assertContains('stall_location', $meta['placeholders']);
        $this->assertContains('monthly_rental', $meta['placeholders']);
        $template = ContractTemplate::create([
            'name'=>'Fictional v04 review', 'version'=>'v04-test', 'path'=>$path,
            'source_checksum'=>$meta['source_checksum'], 'checksum'=>$meta['checksum'],
            'approved'=>true, 'effective_date'=>'2020-01-01', 'created_by'=>$this->admin->id,
            'approved_by'=>$this->admin->id, 'approved_at'=>now(),
            'approval_reference'=>'SYNTHETIC ONLY',
        ]);
        $this->actingAs($this->admin)->get(route('contracts.create'))->assertOk()
            ->assertSee('v04-test');
        $contract = app(LeaseContractService::class)->create($this->admin,
            array_replace($this->input(), ['contract_template_id'=>$template->id]));
        $ready = app(LeaseContractService::class)->generate($this->admin,$contract,
            ['lessor_name'=>'HON. ART JOSEPH FRANCIS MERCADO']);
        $xml = app(ContractTemplateService::class)->readDocument(storage_path('app/'.$ready->docx_path));
        $this->assertStringContainsString($ready->control_number,$xml);
        $this->assertSame(2, substr_count($xml,$ready->control_number));
        $this->assertStringContainsString(' (INNER/CORNER)',$xml);
        $this->assertStringContainsString(': Inner</w:t>',$xml);
        $this->assertStringContainsString('SECTION 24. DISPUTE RESOLUTION AND VENUE',$xml);
        $this->assertStringNotContainsString('${',$xml);
        $this->assertSame($originalHash,hash_file('sha256',$source));
        $this->assertSame(ContractTemplateService::V04_SHA256,$this->template->source_checksum);
        $legacySource = storage_path('app/private/references/v02_San_Pedro_Public_Market_Lease_Template.docx');
        $legacyPrepared = storage_path('app/'.$this->folder.'/v02-working.docx');
        $legacyMeta = app(ContractTemplateService::class)->prepare($legacySource,$legacyPrepared);
        $this->assertSame(ContractTemplateService::SOURCE_SHA256,$legacyMeta['source_checksum']);
        $legacyRendered = storage_path('app/'.$this->folder.'/v02-historical.docx');
        app(\App\Services\ContractGenerationService::class)->render($legacyPrepared,$ready->snapshot,$legacyRendered);
        $this->assertStringContainsString($ready->control_number,
            app(ContractTemplateService::class)->readDocument($legacyRendered));
        $legacyTemplate = ContractTemplate::create([
            'name'=>'Historical v02', 'version'=>'v02-history-test',
            'path'=>$this->folder.'/v02-working.docx', 'source_checksum'=>$legacyMeta['source_checksum'],
            'checksum'=>$legacyMeta['checksum'], 'created_by'=>$this->admin->id,
        ]);
        $this->actingAs($this->admin)->get(route('contracts.create'))->assertOk()
            ->assertDontSee('v02-history-test');
        try {
            app(LeaseContractService::class)->create($this->admin,
                array_replace($this->input(), ['contract_template_id'=>$legacyTemplate->id]));
            $this->fail('A new draft selected the historical v02 template.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contract', $exception->errors());
        }
        $sample = base_path('.qa/v04/sample-contract-synthetic.docx');
        if (!is_dir(dirname($sample))) {
            mkdir(dirname($sample),0775,true);
        }
        $this->assertTrue(copy(storage_path('app/'.$ready->docx_path),$sample));
    }

    private function ready()
    {
        $c = app(LeaseContractService::class)->create($this->admin, $this->input());
        return app(LeaseContractService::class)->generate($this->admin, $c, [
            "lessor_name" => "HON. ART JOSEPH FRANCIS MERCADO",
        ]);
    }

    private function rentalRequest($daily, $start, $end = null)
    {
        $actor = User::firstOrCreate(['email' => 'financial-request@example.test'], ['name' => 'Synthetic requester',
            'password' => 'unused', 'active' => true]);
        $actor->assignRole('Market Staff');
        $service = app(\App\Services\RentalOverrideService::class);
        $r = $service->saveDraft($actor, $this->assignment, ['submission_key' => (string) Str::uuid(),
            'override_type' => 'daily_amount', 'override_daily_amount' => $daily, 'effective_from' => $start,
            'effective_until' => $end, 'reason' => 'Synthetic contract rental terms']);
        return $service->submit($actor, $r);
    }

    private function approveRental($r)
    {
        return app(\App\Services\RentalOverrideService::class)->approve($this->admin, $r, \App\Services\RentalOverrideService::fingerprint($r));
    }

    public function test_contract_term_cannot_straddle_approved_rental_boundaries()
    {
        $start = today()->startOfMonth()->addMonth();
        $this->approveRental($this->rentalRequest('210.00', $start->toDateString(), $start->copy()->endOfMonth()->toDateString()));
        $this->expectException(ValidationException::class);
        app(LeaseContractService::class)->create($this->admin, $this->input());
    }

    public function test_inconsistent_direct_daily_blocks_generation_and_issuance()
    {
        $start = today()->startOfMonth()->addMonth();
        $this->approveRental($this->rentalRequest('210.01', $start->toDateString()));
        $s = app(LeaseContractService::class);
        $c = $s->create($this->admin, array_replace($this->input(), ['start_date' => $start->toDateString()]));
        $this->assertFalse($c->assessment->contract_rate_consistent);
        $this->assertSame('210.01', $c->daily_rental);
        try {
            $s->generate($this->admin, $c, ['lessor_name' => 'HON. ART JOSEPH FRANCIS MERCADO']);
            $this->fail('Generated a contradictory two-decimal rate.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('two-decimal', $e->errors()['contract'][0]);
        }
        $this->assertNull($c->fresh()->docx_path);
        // A ready record simulates an imported draft awaiting issuance; the issue endpoint must recheck.
        $c->update(['status' => 'ready', 'approved_at' => now(), 'approved_by' => $this->admin->id]);
        try {
            $s->transition($this->admin, $c, 'issue');
            $this->fail('Issued inconsistent financial terms.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('two-decimal', $e->errors()['contract'][0]);
        }
        $this->assertSame('ready', $c->fresh()->status);
    }

    public function test_issued_contract_blocks_approval_inside_term_and_retains_original_file()
    {
        $s = app(LeaseContractService::class);
        $c = $this->ready();
        $s->transition($this->admin, $c, 'approve', ['visual_review_confirmed' => '1', 'visual_review_reference' => 'SYNTHETIC']);
        $issued = $s->transition($this->admin, $c, 'issue');
        $hash = hash_file('sha256', $s->privatePath($issued->docx_path));
        $snapshot = $issued->getRawOriginal('snapshot');
        $request = $this->rentalRequest('210.00', today()->startOfMonth()->addMonth()->toDateString());
        try {
            $this->approveRental($request);
            $this->fail('Repriced part of an issued contract.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('contract', $e->errors());
        }
        $this->assertSame($snapshot, $issued->fresh()->getRawOriginal('snapshot'));
        $this->assertSame($hash, hash_file('sha256', $s->privatePath($issued->docx_path)));
        $this->assertSame('issued', $issued->fresh()->status);
    }

    public function test_retirement_respects_frozen_contract_term_after_assignment_closes()
    {
        $start = today()->startOfMonth()->addMonth();
        $end = $start->copy()->addMonths(3)->endOfMonth();
        $this->approveRental($this->rentalRequest('210.00', $start->toDateString()));
        $contract = app(LeaseContractService::class)->create($this->admin, array_replace($this->input(),
            ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()]));
        app(AssignmentService::class)->end($this->admin, $this->assignment, today()->toDateString(), 'Synthetic closure');
        $policy = \App\Models\RentalPolicy::firstOrFail();
        $revision = app(\App\Services\BusinessCategoryService::class);
        try {
            $revision->retire($this->admin, $policy, ['effective_until' => $start->copy()->endOfMonth()->toDateString(), 'reason' => 'Synthetic closure']);
            $this->fail('A frozen lease term still extends past this date.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('effective_until', $e->errors());
        }
        $revision->retire($this->admin, $policy, ['effective_until' => $end->toDateString(), 'reason' => 'Synthetic closure']);
        $this->assertSame('210.00', $contract->fresh()->daily_rental);
    }

    public function test_profile_lease_preparation_retains_tenant_scope_and_rejects_another_assignment()
    {
        $otherTenant = Tenant::create(['reference'=>'OTHER-SCOPE','name'=>'Other synthetic tenant','status'=>'active']);
        $otherBusiness = $this->assignment->business->replicate()->fill(['tenant_id'=>$otherTenant->id]);
        $otherBusiness->save();
        $otherStall = $this->assignment->stall->replicate()->fill(['code'=>'OTHER-SCOPE-STALL','status'=>'vacant']);
        $otherStall->save();
        $otherAssignment = app(AssignmentService::class)->assign($this->admin,[
            'tenant_id'=>$otherTenant->id,'business_id'=>$otherBusiness->id,'stall_id'=>$otherStall->id,
            'start_date'=>today()->startOfMonth()->toDateString(),'agreement_reference'=>'OTHER-SCOPE-AG',
        ]);
        $this->actingAs($this->admin)->get('/contracts/create?tenant_id='.$this->assignment->tenant_id)
            ->assertOk()->assertSee($this->assignment->stall->code)->assertDontSee('OTHER-SCOPE-STALL');
        $this->post('/contracts',array_replace($this->input(),[
            'tenant_id'=>$this->assignment->tenant_id,'stall_assignment_id'=>$otherAssignment->id,
        ]))->assertSessionHasErrors('contract');
        $this->assertDatabaseCount('lease_contracts',0);
    }

    public function test_lease_registry_status_filters_apply_to_the_page_and_export()
    {
        $service = app(LeaseContractService::class);
        $void = $service->create($this->admin,$this->input());
        $service->transition($this->admin,$void,'void',['reason'=>'Synthetic cancellation']);
        $draft = $service->create($this->admin,$this->input());
        $this->actingAs($this->admin)->get('/reports/lease-contract-registry?status=void')->assertOk()
            ->assertSee($void->control_number)->assertDontSee($draft->control_number);
        $csv = $this->get('/reports/lease-contract-registry?status=void&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString($void->control_number,$csv);
        $this->assertStringNotContainsString($draft->control_number,$csv);
        $this->get('/reports/lease-contract-registry?status=ready')->assertOk();
    }
    public function test_legacy_assignment_requires_renewal_before_creating_a_contract()
    {
        $stall = $this->assignment->stall->replicate()->fill(["code" => "LEGACY"]);
        $stall->save();
        $assignment = StallAssignment::create([
            "tenant_id" => $this->assignment->tenant_id, "stall_id" => $stall->id,
            "active_stall_id" => $stall->id, "start_date" => "2020-01-01",
            "status" => "active", "authorized_by" => $this->admin->id,
        ]);
        $rate = RentalRate::create([
            "market_id" => $stall->market_id, "classification" => $stall->classification,
            "amount" => "2500.00", "effective_from" => "2020-01-01", "approval_reference" => "TEST",
        ]);
        RentalAgreement::create([
            "stall_assignment_id" => $assignment->id, "rental_rate_id" => $rate->id,
            "monthly_amount" => "2500.00", "reference" => "LEGACY", "start_date" => "2020-01-01",
        ]);
        $this->actingAs($this->admin)->post("/contracts", array_replace($this->input(),
            ["stall_assignment_id" => $assignment->id]))->assertSessionHasErrors("contract");
        $this->assertDatabaseCount("lease_contracts", 0);
    }

    public function test_changed_trade_details_need_renewal_and_manual_values_are_rejected()
    {
        $this->actingAs($this->admin)
            ->post("/contracts", $this->input() + ["rental_rate_sqm" => "1.00"])
            ->assertSessionHasErrors("rental_rate_sqm");
        $this->assignment->business->update(["permitted_goods" => "Changed goods"]);
        $this->post("/contracts", $this->input())->assertSessionHasErrors("contract");
        $this->assertDatabaseCount("lease_contracts", 0);
    }

    public function test_first_generation_uses_verified_identity_and_helpers_completed_after_assignment()
    {
        $tenant = $this->assignment->tenant;
        $tenant->update(["government_id_number" => null]);
        $this->assignment = app(AssignmentService::class)->transfer($this->admin, $this->assignment, [
            "stall_id" => $this->assignment->stall_id, "business_id" => $this->assignment->business_id,
            "start_date" => today()->startOfMonth()->toDateString(), "agreement_reference" => "SYNTHETIC-RENEWED",
            "reason" => "Synthetic renewal review",
        ]);
        $initial = $this->assignment->agreement->assessment;
        $this->assertNull($initial->snapshot["tenant"]["government_id_number"]);

        $tenant->update(["government_id_number" => "NEW-SYNTHETIC-ID"]);
        app(TenantDataService::class)->saveHelpers($this->admin, $this->assignment,
            [["full_name" => "New synthetic helper", "status" => "authorized"]]);
        $contract = $this->ready();
        $this->assertSame("NEW-SYNTHETIC-ID", $contract->snapshot["government_id_number"]);
        $this->assertSame("New synthetic helper", $contract->snapshot["helper_1"]);
        $this->assertNull($initial->fresh()->snapshot["tenant"]["government_id_number"]);
        $this->assertSame("", $contract->assessment->snapshot["tenant"]["helper_1"]);
    }

    public function test_contract_generation_and_issued_snapshot_preserve_history_and_original_download()
    {
        $service = app(LeaseContractService::class);
        $c = $this->ready();
        $this->assertSame("ready", $c->status);
        $this->assertSame("6300.00", (string) $c->total_monthly_rental_cusa);
        $this->assertSame("Synthetic permitted goods", $c->permitted_goods);
        $this->assertSame("contract", $c->assessment->purpose);
        $this->assertSame(substr($c->start_date, 0, 7), $c->assessment->period);
        $this->assertSame("Fictional shop category", $c->snapshot["business_category_name"]);
        $this->assertStringContainsString($c->assessment->period, $c->snapshot["billing_basis"]);
        $this->assertNull($c->pdf_path);
        $this->assertStringNotContainsString("TEST-PRIVATE-ID", $c->getRawOriginal("snapshot"));
        $service->transition($this->admin, $c, "approve", [
            "visual_review_reference" => "Synthetic page review",
            "visual_review_confirmed" => "1",
        ]);
        $service->transition($this->admin, $c, "issue");
        $before = hash_file("sha256", storage_path("app/" . $c->docx_path));
        $this->assignment->tenant->update([
            "name" => "Changed later",
            "address" => "Changed later",
        ]);
        $this->assertSame("SYNTHETIC, PERSON, A.", $c->fresh()->snapshot["lessee_full_name"]);
        $this->assertSame($before, hash_file("sha256", storage_path("app/" . $c->docx_path)));
        $this->actingAs($this->admin)
            ->get("/contracts/" . $c->id)
            ->assertOk();
        $this->get("/contracts/" . $c->id . "/download/docx")
            ->assertOk()
            ->assertDownload();
        $service->transition($this->admin, $c, "void", [
            "reason" => "Synthetic correction request",
        ]);
        $this->assertSame("void", $c->fresh()->status);
        $this->assertSame("occupied", $this->assignment->stall->fresh()->status);
    }
    public function test_invalid_dates_duplicate_requests_and_overlap_are_handled_safely()
    {
        $service = app(LeaseContractService::class);
        $d = $this->input();
        $c = $service->create($this->admin, $d);
        $this->assertSame($c->id, $service->create($this->admin, $d)->id);
        $this->assertSame(1, LeaseContract::count());
        $this->expectException(ValidationException::class);
        $service->create($this->admin, $this->input());
    }
    public function test_invalid_lease_period_is_rejected()
    {
        $d = $this->input();
        $d["end_date"] = $d["start_date"];
        $this->expectException(ValidationException::class);
        app(LeaseContractService::class)->create($this->admin, $d);
    }
    public function test_staff_cannot_generate_approve_or_download_private_contracts()
    {
        $c = $this->ready();
        $staff = User::create([
            "name" => "Synthetic staff",
            "email" => "staff-lease@example.test",
            "password" => Hash::make(Str::random(40)),
            "active" => true,
        ]);
        $staff->assignRole("Market Staff");
        $this->actingAs($staff);
        $this->get("/contracts")->assertOk();
        $this->get("/contracts/" . $c->id . "/download/docx")->assertForbidden();
        $this->post("/contracts/" . $c->id . "/action/approve", [
            "visual_review_reference" => "invalid",
            "visual_review_confirmed" => 1,
        ])->assertForbidden();
        $this->get("/contract-templates")->assertForbidden();
    }
    public function test_issuance_requires_authorized_visual_review_and_policy()
    {
        $c = $this->ready();
        $this->expectException(ValidationException::class);
        app(LeaseContractService::class)->transition($this->admin, $c, "issue");
    }
    public function test_unapproved_policy_stops_reviewed_issuance()
    {
        $c = $this->ready();
        $s = app(LeaseContractService::class);
        $s->transition($this->admin, $c, "approve", [
            "visual_review_reference" => "Synthetic review",
            "visual_review_confirmed" => 1,
        ]);
        config(["lease.rental_policy_approval" => null]);
        $this->expectException(ValidationException::class);
        $s->transition($this->admin, $c, "issue");
    }
    public function test_generated_financial_snapshot_is_immutable()
    {
        $c = $this->ready();
        $this->expectException(\LogicException::class);
        $c->update(["monthly_rental" => "1.00"]);
    }
    public function test_contract_screens_and_templates_render()
    {
        $this->actingAs($this->admin);
        foreach (["/contracts", "/contracts/create", "/contract-templates"] as $url) {
            $this->get($url)->assertOk();
        }
    }
    public function test_signed_upload_and_activation_preserve_existing_occupancy()
    {
        $s = app(LeaseContractService::class);
        $c = $this->ready();
        $s->transition($this->admin, $c, "approve", [
            "visual_review_reference" => "Synthetic visual review",
            "visual_review_confirmed" => 1,
        ]);
        $s->transition($this->admin, $c, "issue");
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            "signed.pdf",
            "%PDF-1.4\nSynthetic executed fixture\n%%EOF",
        );
        $s->uploadSigned($this->admin, $c, $file);
        $this->assertSame("signed", $c->fresh()->status);
        $s->transition($this->admin, $c, "activate");
        $this->assertSame("active", $c->fresh()->status);
        $this->assertSame("occupied", $this->assignment->stall->fresh()->status);
    }
    public function test_generated_download_integrity_and_traversal_are_rejected()
    {
        $c = $this->ready();
        $this->actingAs($this->admin);
        file_put_contents(storage_path("app/" . $c->docx_path), "modified");
        $this->get("/contracts/" . $c->id . "/download/docx")->assertStatus(409);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LeaseContractService::class)->privatePath("../.env");
    }
    public function test_closed_assignment_cannot_receive_contract()
    {
        $this->assignment->update([
            "status" => "ended",
            "active_stall_id" => null,
            "end_date" => "2020-01-02",
        ]);
        $this->expectException(ValidationException::class);
        app(LeaseContractService::class)->create($this->admin, $this->input());
    }
    public function test_numbering_format_is_configurable_and_numbers_unique()
    {
        config([
            "lease.control_prefix" => "TEST",
            "lease.number_format" => "{prefix}-{sequence}-{period}",
        ]);
        $service = app(\App\Services\ContractNumberService::class);
        $numbers = \Illuminate\Support\Facades\DB::transaction(function () use ($service) {
            return [$service->next(), $service->next()];
        });
        $this->assertNotSame($numbers[0], $numbers[1]);
        $this->assertStringStartsWith("TEST-000001-", $numbers[0]);
    }
}
