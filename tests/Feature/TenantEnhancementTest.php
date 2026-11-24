<?php
namespace Tests\Feature;

use App\Models\{Tenant, User, Market, MarketSection, Stall, StallAssignment, TenantHelper};
use App\Services\TenantDataService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->actor = User::create(['name'=>'Synthetic administrator','email'=>'tenant-test@example.test','password'=>'unused','active'=>true]);
        $this->actor->assignRole('Super Administrator');
        $this->actingAs($this->actor);
    }

    private function profile(array $extra = [])
    {
        return $extra + ['profile_form'=>'1','last_name'=>'Example','first_name'=>'Ana','middle_name'=>'Beatriz','status'=>'active', 'present_account_number'=>'0007-02','government_id_number'=>'SYNTHETIC-ID-123'];
    }

    public function test_structured_profile_generates_reference_formats_name_and_encrypts_id()
    {
        $this->post('/manage/tenants', $this->profile())->assertSessionHasNoErrors()->assertRedirect();
        $tenant = Tenant::firstOrFail();
        $this->assertStringStartsWith('T-', $tenant->reference);
        $this->assertSame('EXAMPLE, ANA, B.', $tenant->name);
        $this->assertSame('0007-02', $tenant->present_account_number);
        $this->assertSame('SYNTHETIC-ID-123', $tenant->government_id_number);
        $this->assertNotSame('SYNTHETIC-ID-123', $tenant->getRawOriginal('government_id_number'));
        $this->assertArrayNotHasKey('government_id_number', $tenant->toArray());
        $this->get('/manage/tenants')->assertOk()->assertDontSee('SYNTHETIC-ID-123');
    }

    public function test_legacy_names_are_preserved_and_new_form_requires_structured_names()
    {
        $tenant = Tenant::create(['reference'=>'LEGACY','name'=>'Original unsplit name','status'=>'active']);
        $this->get('/manage/tenants/'.$tenant->id)->assertOk()->assertSee('Original unsplit name');
        $this->get('/manage/tenants/'.$tenant->id.'/edit')->assertOk()->assertSee('Original unsplit name');
        $this->put('/manage/tenants/'.$tenant->id, ['profile_form'=>'1','status'=>'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('Original unsplit name', $tenant->fresh()->name);
        $this->post('/manage/tenants', ['profile_form'=>'1','status'=>'active'])->assertSessionHasErrors(['last_name','first_name']);
    }

    private function assignment(Tenant $tenant)
    {
        $market = Market::create(['code'=>'SYN','name'=>'Synthetic market']);
        $section = MarketSection::create(['market_id'=>$market->id,'code'=>'S','name'=>'Synthetic section']);
        $stall = Stall::create(['market_id'=>$market->id,'market_section_id'=>$section->id,'code'=>'S-01','classification'=>'Synthetic','area'=>'10.00']);
        return StallAssignment::create(['tenant_id'=>$tenant->id,'stall_id'=>$stall->id,'active_stall_id'=>$stall->id,'start_date'=>'2026-01-01','status'=>'active','authorized_by'=>$this->actor->id]);
    }

    public function test_helpers_are_bounded_to_two_and_owned_assignment_and_archiving_protects_occupancy()
    {
        $tenant = Tenant::create(['reference'=>'SYN1','name'=>'Synthetic Tenant','status'=>'active']);
        $assignment = $this->assignment($tenant);
        $helper = ['full_name'=>'Synthetic Helper','relationship'=>'Sibling','status'=>'authorized'];
        $url = '/manage/tenants/'.$tenant->id;
        $data = ['profile_form'=>'1','status'=>'active','helper_assignment_id'=>$assignment->id,'helpers'=>[$helper,$helper]];
        $this->put($url, $data)->assertSessionHasNoErrors();
        $this->assertSame(2, TenantHelper::count());
        $this->put($url, array_replace($data, ['helpers'=>[$helper,$helper,$helper]]))->assertSessionHasErrors('helpers');
        $this->put($url, ['profile_form'=>'1','status'=>'archived'])->assertSessionHasErrors();
        $other = Tenant::create(['reference'=>'SYN2','name'=>'Other Synthetic','status'=>'active']);
        $this->put('/manage/tenants/'.$other->id, $data)->assertSessionHasErrors('helper_assignment_id');
        $this->assertSame(2, TenantHelper::count());
        $this->assertSame(1, Stall::count());
    }

    public function test_snapshot_returns_authorized_helpers_only_and_isolation_from_later_updates()
    {
        $tenant = Tenant::create(['reference'=>'SNAP','name'=>'Synthetic Person','government_id_number'=>'SYN-ID','status'=>'active']);
        $assignment = $this->assignment($tenant);
        TenantHelper::create(['tenant_id'=>$tenant->id,'stall_assignment_id'=>$assignment->id,'slot'=>1,'full_name'=>'Authorized Synthetic','status'=>'authorized']);
        TenantHelper::create(['tenant_id'=>$tenant->id,'stall_assignment_id'=>$assignment->id,'slot'=>2,'full_name'=>'Inactive Synthetic','status'=>'inactive']);
        $snapshot = app(TenantDataService::class)->contractData($tenant,$assignment);
        $this->assertSame('Authorized Synthetic',$snapshot['helper_1']);
        $this->assertSame('',$snapshot['helper_2']);
        $this->assertSame('SYN-ID',$snapshot['government_id_number']);
        $tenant->update(['name'=>'Later name']);
        $this->assertSame('Synthetic Person',$snapshot['lessee_full_name']);
    }

    public function test_sensitive_access_masking_validation_flash_and_audit_privacy()
    {
        $this->post('/manage/tenants',$this->profile())->assertSessionHasNoErrors();
        $tenant = Tenant::firstOrFail();
        $this->get('/manage/tenants/'.$tenant->id)->assertOk()->assertDontSee('SYNTHETIC-ID-123');
        $this->get('/manage/tenants/'.$tenant->id.'?tab=personal')->assertOk()->assertSee('SYNTHETIC-ID-123');
        $staff = User::create(['name'=>'Synthetic staff','email'=>'staff-tenant@example.test','password'=>'unused','active'=>true]);
        $staff->assignRole('Market Staff');
        $this->actingAs($staff);
        $this->get('/manage/tenants/'.$tenant->id.'?tab=personal')->assertOk()->assertDontSee('SYNTHETIC-ID-123')->assertSee('••••-123');
        $this->get('/manage/tenants/'.$tenant->id.'/edit')->assertOk()->assertDontSee('SYNTHETIC-ID-123');
        $this->put('/manage/tenants/'.$tenant->id,$this->profile(['status'=>'invalid']))->assertSessionHasErrors('status');
        $this->assertNull(session()->getOldInput('government_id_number'));
        foreach (\App\Models\AuditLog::all() as $audit) {
            $this->assertStringNotContainsString('SYNTHETIC-ID-123',json_encode($audit->toArray()));
            $this->assertStringNotContainsString('0007-02',json_encode($audit->toArray()));
        }
        $viewer = User::create(['name'=>'Synthetic viewer','email'=>'viewer-tenant@example.test','password'=>'unused','active'=>true]);
        $viewer->assignRole('LGU Management Viewer');
        $this->actingAs($viewer);
        $this->get('/manage/tenants')->assertForbidden();
        $this->get('/manage/tenants/'.$tenant->id)->assertForbidden();
        $this->post('/manage/tenants',$this->profile())->assertForbidden();
    }

    public function test_helper_edits_are_transactional_and_legacy_accounts_remain_strings()
    {
        $tenant = Tenant::create(['reference'=>'HISTORY','name'=>'Unsplit history','status'=>'active','present_account_number'=>'000000-01']);
        $assignment = $this->assignment($tenant);
        $url = '/manage/tenants/'.$tenant->id;
        $data = ['status'=>'active','profile_form'=>'1','helper_assignment_id'=>$assignment->id,'helpers'=>[['full_name'=>'Synthetic Helper','status'=>'authorized']]];
        $this->put($url,$data)->assertSessionHasNoErrors();
        $this->put($url,array_replace($data,['helpers'=>[['full_name'=>'Synthetic Helper','status'=>'inactive']]]))->assertSessionHasNoErrors();
        $this->assertSame('',app(TenantDataService::class)->contractData($tenant,$assignment)['helper_1']);
        $this->assertSame('000000-01',$tenant->fresh()->present_account_number);
        $this->assertSame(1,TenantHelper::count());
        $this->put($url,['status'=>'active','name'=>'Must rollback','helpers'=>[['full_name'=>'No assignment']]])->assertSessionHasErrors('helper_assignment_id');
        $this->assertSame('Unsplit history',$tenant->fresh()->name);
    }
}
