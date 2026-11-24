<?php
namespace Tests\Feature;

use App\Models\{Business, BusinessCategory, BusinessCategoryRentalRate, ContractTemplate, LeaseContract, Market, MarketSection, RentalAgreement, RentalAssessment, RentalPolicy, Stall, StallAssignment, Tenant, TenantHelper, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedTenantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->actor = $this->operator('Super Administrator', 'registration');
        $this->actingAs($this->actor);
    }

    public function test_registration_submission_retry_returns_the_same_tenant_and_rejects_changed_content()
    {
        $profile = $this->profile();
        $first = $this->post('/manage/tenants', $profile)->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/manage/tenants', $profile)->assertSessionHasNoErrors()->assertRedirect($first->headers->get('Location'));
        $this->post('/manage/tenants', array_replace($profile, ['first_name'=>'Other']))->assertSessionHasErrors('submission_key');
        $this->assertSame(1, Tenant::count());
    }

    public function test_nested_business_allows_blank_name_and_protects_ownership_and_archive()
    {
        $category = $this->category();
        $tenant = Tenant::create(['reference'=>'ONE','name'=>'Fictional One','status'=>'active']);
        $other = Tenant::create(['reference'=>'TWO','name'=>'Fictional Two','status'=>'active']);
        $data = ['name'=>'','business_category_id'=>$category->id,'permitted_goods'=>'Fictional apples','status'=>'active'];
        $this->post('/manage/tenants/'.$tenant->id.'/businesses', $data)->assertSessionHasNoErrors()->assertRedirect();
        $business = Business::firstOrFail();
        $this->assertNull($business->name);
        $this->assertSame($tenant->id, (int)$business->tenant_id);
        $this->put('/manage/tenants/'.$other->id.'/businesses/'.$business->id, $data)->assertNotFound();
        $stall = $this->stall();
        StallAssignment::create(['stall_id'=>$stall->id,'active_stall_id'=>$stall->id,'tenant_id'=>$tenant->id,'business_id'=>$business->id,'status'=>'active','start_date'=>today()->toDateString(),'authorized_by'=>$this->actor->id]);
        $this->post('/manage/tenants/'.$tenant->id.'/businesses/'.$business->id.'/archive')->assertSessionHasErrors('status');
        $this->assertSame('active', $business->fresh()->status);
    }

    public function test_draft_choices_are_encrypted_and_resume_without_reserving_a_stall()
    {
        $category = $this->category();
        $stall = $this->stall();
        $this->post('/manage/tenants', $this->profile() + $this->choices($category, $stall))->assertSessionHasNoErrors()->assertRedirect();
        $tenant = Tenant::firstOrFail();
        $this->assertSame('draft', $tenant->status);
        $this->assertSame('Fictional Helper', $tenant->registration_draft['assignments'][0]['helpers'][0]['full_name']);
        $this->assertStringNotContainsString('Fictional Helper', $tenant->getRawOriginal('registration_draft'));
        $this->assertArrayNotHasKey('registration_draft', $tenant->toArray());
        $this->assertSame($stall->id, (int)$tenant->registration_draft['assignments'][0]['stall_id']);
        $this->assertSame('vacant', $stall->fresh()->status);
        $this->assertSame(0, StallAssignment::count());
        $this->assertSame(0, TenantHelper::count());
        $this->get('/manage/tenants/'.$tenant->id.'/edit')->assertOk()->assertSee('Fictional Helper')->assertSee('Confirm registration');
    }

    public function test_maximum_pending_draft_round_trips_multibyte_helper_remarks()
    {
        $category = $this->category();
        $stall = $this->stall();
        $choices = $this->choices($category, $stall);
        $remarks = str_repeat('🛒', 2000);
        $pending = [];
        for ($index = 0; $index < 10; $index++) {
            $selection = $choices['pending_assignments'][0];
            $selection['stall_id'] = $index === 0 ? $stall->id : $this->stall('STALL-'.$index, $stall->market)->id;
            $selection['helpers'] = [
                ['full_name'=>'Fictional first helper '.$index,'remarks'=>$remarks],
                ['full_name'=>'Fictional second helper '.$index,'remarks'=>$remarks],
            ];
            $pending[] = $selection;
        }
        $choices['pending_assignments'] = $pending;
        $this->post('/manage/tenants', $this->profile() + $choices)->assertSessionHasNoErrors()->assertRedirect();
        $tenant = Tenant::firstOrFail();
        $draft = $tenant->registration_draft;
        $this->assertCount(10, $draft['assignments']);
        $this->assertGreaterThan(65535, strlen($tenant->getRawOriginal('registration_draft')));
        foreach ($draft['assignments'] as $assignment) {
            $this->assertSame([$remarks, $remarks], array_column($assignment['helpers'], 'remarks'));
        }
        $this->get('/manage/tenants/'.$tenant->id.'/edit')->assertOk()->assertSee('Fictional second helper 9')->assertSee($remarks);
        $this->assertSame(0, TenantHelper::count());
        $this->assertSame(0, StallAssignment::count());
    }

    public function test_lease_filters_distinguish_generated_and_approved_ready_records()
    {
        $stall = $this->stall();
        $template = ContractTemplate::create([
            'name'=>'Fictional template','version'=>'test-filter','path'=>'unused.docx',
            'source_checksum'=>str_repeat('a', 64),'checksum'=>str_repeat('b', 64),'created_by'=>$this->actor->id,
        ]);
        foreach (['GENERATED'=>null,'APPROVED'=>now()] as $state=>$approvedAt) {
            $tenant = Tenant::create(['reference'=>'TENANT-'.$state,'name'=>'Fictional '.$state,'status'=>'active']);
            $assignment = StallAssignment::create([
                'stall_id'=>$stall->id,'tenant_id'=>$tenant->id,'status'=>'ended',
                'start_date'=>'2025-01-01','end_date'=>'2025-01-31','authorized_by'=>$this->actor->id,
            ]);
            LeaseContract::create([
                'tenant_id'=>$tenant->id,'stall_assignment_id'=>$assignment->id,'contract_template_id'=>$template->id,
                'control_number'=>'LEASE-'.$state,'submission_key'=>(string)Str::uuid(),'request_hash'=>str_repeat('a',64),
                'status'=>'ready','start_date'=>'2025-01-01','end_date'=>'2025-01-31','execution_date'=>'2025-01-01',
                'billing_days'=>31,'permitted_goods'=>'Fictional goods','created_by'=>$this->actor->id,
                'generated_at'=>now(),'approved_at'=>$approvedAt,
            ] + array_fill_keys(['floor_area','rental_rate_sqm','daily_cusa','daily_rental','monthly_rental','monthly_cusa',
                'total_monthly_rental_cusa','advance_rental','security_deposit','total_initial_payment'], '1.00'));
        }
        $this->get('/manage/tenants?lease_status=generated')->assertOk()->assertSee('TENANT-GENERATED')->assertDontSee('TENANT-APPROVED');
        $this->get('/manage/tenants?lease_status=approved')->assertOk()->assertSee('TENANT-APPROVED')->assertDontSee('TENANT-GENERATED');
        $this->get('/manage/tenants?lease_status=ready')->assertOk()->assertSee('TENANT-GENERATED')->assertSee('TENANT-APPROVED');
    }

    public function test_draft_confirmation_requires_assignment_permission_and_rechecks_stall_availability()
    {
        $category = $this->category();
        $stall = $this->stall();
        $this->approveRates($category, $stall->market);
        $this->post('/manage/tenants', $this->profile() + $this->choices($category, $stall))->assertSessionHasNoErrors();
        $tenant = Tenant::firstOrFail();
        $choices = $this->choices($category, $stall);
        $choices['businesses']['fruit']['id'] = $tenant->businesses()->firstOrFail()->id;
        $confirmation = $this->profile(['registration_action'=>'confirm','registration_version'=>$tenant->registration_draft['version']]) + $choices;
        $url = '/manage/tenants/'.$tenant->id;
        $this->actingAs($this->operator('Market Staff', 'draft-staff'))->put($url, $confirmation)->assertForbidden();
        $stall->update(['status'=>'maintenance']);
        $this->actingAs($this->actor)->from($url.'/edit')->put($url, $confirmation)->assertSessionHasErrors('assignment');
        $this->assertSame('draft', $tenant->fresh()->status);
        $this->assertSame(0, TenantHelper::count());
        $stall->update(['status'=>'vacant']);
        $this->put($url, $confirmation)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertSame(1, TenantHelper::count());
        $this->assertSame('Fictional Helper', RentalAssessment::firstOrFail()->snapshot['tenant']['helper_1']);
    }

    public function test_nested_business_changes_make_a_saved_registration_form_stale()
    {
        $category = $this->category();
        $choices = ['businesses'=>['fruit'=>['name'=>'Fictional fruit stall','business_category_id'=>$category->id,'status'=>'active']]];
        $this->post('/manage/tenants', $this->profile() + $choices)->assertSessionHasNoErrors();
        $tenant = Tenant::firstOrFail();
        $business = $tenant->businesses()->firstOrFail();
        $version = $tenant->registration_draft['version'];
        $url = '/manage/tenants/'.$tenant->id;
        $this->put($url.'/businesses/'.$business->id, ['name'=>'Verified fictional trade','business_category_id'=>$category->id,'status'=>'active'])->assertSessionHasNoErrors();

        $choices['businesses']['fruit']['id'] = $business->id;
        $this->from($url.'/edit')->put($url, $this->profile(['registration_action'=>'confirm','registration_version'=>$version]) + $choices)->assertSessionHasErrors('registration_version');
        $this->assertSame('Verified fictional trade', $business->fresh()->name);
        $this->assertSame('draft', $tenant->fresh()->status);
        $this->get($url.'/edit')->assertOk()->assertSee('Verified fictional trade')->assertDontSee('Fictional fruit stall');
    }

    public function test_validation_retry_does_not_refresh_an_outdated_registration_version()
    {
        $category = $this->category();
        $stall = $this->stall();
        $choices = $this->choices($category, $stall);
        $this->post('/manage/tenants', $this->profile() + $choices)->assertSessionHasNoErrors();
        $tenant = Tenant::firstOrFail();
        $url = '/manage/tenants/'.$tenant->id;
        $version = $tenant->registration_draft['version'];
        $choices['businesses']['fruit']['id'] = $tenant->businesses()->firstOrFail()->id;
        $this->put($url, $this->profile(['first_name'=>'Verified','registration_version'=>$version]) + $choices)->assertSessionHasNoErrors();

        $invalidChoices = $choices;
        $invalidChoices['pending_assignments'][0]['start_date'] = 'invalid';
        $outdated = $this->profile(['first_name'=>'Outdated','registration_version'=>$version]);
        $this->from($url.'/edit')->put($url, $outdated + $invalidChoices)->assertSessionHasErrors('pending_assignments.0.start_date');
        $view = $this->get($url.'/edit')->assertOk();
        preg_match('/name="registration_version" value="([^"]+)"/', $view->getContent(), $hidden);
        $this->assertSame($version, $hidden[1] ?? null);
        $outdated['registration_version'] = $hidden[1];
        $this->from($url.'/edit')->put($url, $outdated + $choices)->assertSessionHasErrors('registration_version');
        $this->assertSame('Verified', $tenant->fresh()->first_name);
    }

    public function test_confirmation_is_atomic_with_multiple_businesses_and_no_implicit_lease()
    {
        $category = $this->category();
        $first = $this->stall('FIRST');
        $second = $this->stall('SECOND', $first->market, 'maintenance');
        $this->approveRates($category, $first->market);
        $choices = $this->choices($category, $first);
        $choices['businesses']['other'] = ['business_category_id'=>$category->id,'name'=>'Other fictional trade','permitted_goods'=>'Fictional second trade goods','status'=>'active'];
        $choices['pending_assignments'][] = ['business_key'=>'other','stall_id'=>$second->id,'start_date'=>today()->startOfMonth()->toDateString(),'concurrent_authorization_reference'=>'AUTH-DEMO','concurrent_authorization_reason'=>'Fictional authorized second stall'];
        $form = $this->profile(['registration_action'=>'confirm']) + $choices;
        $this->from('/manage/tenants/create')->post('/manage/tenants', $form)->assertRedirect('/manage/tenants/create')->assertSessionHasErrors();
        $this->assertSame(0, Tenant::count());
        $this->assertSame(0, Business::count());
        $this->assertSame(0, RentalAgreement::count());
        $this->assertSame(0, RentalAssessment::count());
        $this->assertSame(0, TenantHelper::count());
        $this->assertSame('vacant', $first->fresh()->status);
        $second->update(['status'=>'vacant']);
        $this->post('/manage/tenants', $form)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, Tenant::count());
        $this->assertSame(2, Business::count());
        $this->assertSame(2, RentalAgreement::count());
        $this->assertSame(2, RentalAssessment::count());
        $this->assertSame(1, TenantHelper::count());
        $this->assertSame(0, LeaseContract::count());
        $this->assertNull(Tenant::firstOrFail()->registration_draft);
        $this->post('/manage/tenants', $form)->assertSessionHasNoErrors();
        $this->assertSame(2, StallAssignment::count());
    }

    public function test_preview_checks_permissions_ownership_and_outputs_only_calculation_fields()
    {
        $category = $this->category();
        $stall = $this->stall();
        $this->approveRates($category, $stall->market);
        $tenant = Tenant::create(['reference'=>'LOCAL','name'=>'Fictional tenant','status'=>'active']);
        $other = Tenant::create(['reference'=>'OTHER','name'=>'Fictional other tenant','status'=>'active']);
        $business = Business::create(['tenant_id'=>$tenant->id,'business_category_id'=>$category->id,'name'=>'Fictional market business','status'=>'active']);
        $selection = ['stall_id'=>$stall->id,'start_date'=>today()->startOfMonth()->toDateString(),'tenant_id'=>$tenant->id,'business_id'=>$business->id];
        $preview = $this->postJson('/manage/tenants/rental-preview', $selection)->assertOk()->assertJsonPath('daily_rental', '174.20');
        $this->assertArrayNotHasKey('business_id', $preview->json());
        $this->assertArrayNotHasKey('market_id', $preview->json());
        $this->postJson('/manage/tenants/rental-preview', array_replace($selection, ['tenant_id'=>$other->id]))->assertUnprocessable()->assertJsonValidationErrors('business_id');
        $this->actingAs($this->operator('Market Staff', 'staff'))->postJson('/manage/tenants/rental-preview', $selection)->assertForbidden();
        $this->assertSame(0, RentalAssessment::count());
    }

    public function test_same_name_registration_warns_without_merging_and_tenant_list_searches_owned_business()
    {
        Tenant::create(['reference'=>'EXISTING','name'=>'SAMPLE, LINA','status'=>'active']);
        $category = $this->category();
        $this->post('/manage/tenants', $this->profile(['registration_action'=>'confirm']) + [
            'businesses'=>['fruit'=>['name'=>'Fictional fruit stand','business_category_id'=>$category->id,'status'=>'active']],
        ])->assertSessionHasNoErrors()->assertSessionHas('duplicate_warning');
        $this->assertSame(2, Tenant::count());
        $this->get('/manage/tenants?q=Fictional+fruit&business_category_id='.$category->id)->assertOk()->assertSee('Fictional fruit stand')->assertDontSee('EXISTING');
    }

    private function profile(array $overrides = [])
    {
        return $overrides + ['profile_form'=>'1','status'=>'active','last_name'=>'Sample','first_name'=>'Lina','registration_action'=>'draft','submission_key'=>(string) Str::uuid()];
    }

    private function choices(BusinessCategory $category, Stall $stall)
    {
        return [
            'businesses'=>['fruit'=>['business_category_id'=>$category->id,'permitted_goods'=>'Fictional registered goods','status'=>'active']],
            'pending_assignments'=>[['business_key'=>'fruit','stall_id'=>$stall->id,'start_date'=>today()->startOfMonth()->toDateString(),'helpers'=>[['full_name'=>'Fictional Helper']]]],
        ];
    }

    private function category()
    {
        return BusinessCategory::create(['code'=>'FIXTURE-IT','name'=>'Fictional IT','is_active'=>true]);
    }

    private function stall($code = 'EXAMPLE', ?Market $market = null, $status = 'vacant')
    {
        $market = $market ?: Market::create(['code'=>'FIC','name'=>'Fictional market']);
        $section = MarketSection::firstOrCreate(['market_id'=>$market->id,'code'=>'FIC-S'], ['name'=>'Fictional section']);
        return Stall::create(['market_id'=>$market->id,'market_section_id'=>$section->id,'code'=>$code,'classification'=>'Fictional','area'=>'5.00','location_type'=>'inner','status'=>$status]);
    }

    private function approveRates(BusinessCategory $category, Market $market)
    {
        $approval = ['status'=>'approved','effective_from'=>'2020-01-01','reference_document'=>'FICTIONAL-POLICY','created_by'=>$this->actor->id,'approved_by'=>$this->actor->id,'approved_at'=>now()];
        BusinessCategoryRentalRate::create($approval + ['business_category_id'=>$category->id,'minimum_rate'=>'34.84','maximum_rate'=>'40.00']);
        RentalPolicy::create($approval + ['market_id'=>$market->id,'daily_cusa'=>'1.00','billing_day_rule'=>'calendar','max_concurrent_assignments'=>2,'advance_deposit_authorized'=>false]);
    }

    private function operator($role, $key)
    {
        $user = User::create(['name'=>'Fictional '.$key,'email'=>$key.'@example.test','password'=>'unused','active'=>true]);
        $user->assignRole($role);
        return $user;
    }
}
