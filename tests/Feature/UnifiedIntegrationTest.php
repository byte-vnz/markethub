<?php
namespace Tests\Feature;

use App\Models\{User, Market, MarketSection, Stall, Tenant, StallAssignment, AuditLog};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Fixtures\ApprovedRentalConfiguration;

    private function fixtures($role = 'Super Administrator')
    {
        $this->seed(RoleSeeder::class);
        $user = User::create(['name'=>'Synthetic operator','email'=>'operator@example.test','password'=>bcrypt('fixture-only'),'active'=>true]);
        $user->assignRole($role);
        $market = Market::create(['code'=>'SYN','name'=>'Synthetic market','status'=>'active']);
        $section = MarketSection::create(['market_id'=>$market->id,'code'=>'A','name'=>'Synthetic section','status'=>'active']);
        $this->actingAs($user);
        return [$user, $market, $section];
    }

    private function stallData($market, $section)
    {
        return ['market_id'=>$market->id,'market_section_id'=>$section->id,'code'=>'SYN-01','classification'=>'physical','area'=>'10.00','status'=>'vacant'];
    }

    public function test_new_stalls_require_verified_location_and_invalid_location_is_rejected()
    {
        list($user,$market,$section) = $this->fixtures();
        $data = $this->stallData($market,$section);
        $this->post('/manage/stalls',$data)->assertSessionHasErrors('location_type');
        $this->post('/manage/stalls',$data+['location_type'=>'coordinates'])->assertSessionHasErrors('location_type');
        $this->post('/manage/stalls',$data+['location_type'=>'inner'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stalls',['code'=>'SYN-01','location_type'=>'inner']);
    }

    public function test_unclassified_stalls_remain_accessible_and_location_filters_are_distinct()
    {
        list($user,$market,$section) = $this->fixtures();
        $data = $this->stallData($market,$section);
        $stall = Stall::create($data);
        Stall::create(array_merge($data,['code'=>'INNER-ONLY','location_type'=>'inner']));
        Stall::create(array_merge($data,['code'=>'OUTER-ONLY','location_type'=>'outer']));
        $this->get('/manage/stalls?location_type=unclassified')->assertOk()->assertSee('SYN-01')->assertDontSee('INNER-ONLY')->assertDontSee('OUTER-ONLY');
        $this->get('/manage/stalls?location_type=inner')->assertOk()->assertSee('INNER-ONLY')->assertDontSee('OUTER-ONLY');
        $this->get('/manage/stalls/'.$stall->id)->assertOk()->assertSee('Unclassified');
        $this->get('/manage/stalls?location_type=unknown')->assertSessionHasErrors('location_type');
    }

    public function test_location_and_area_changes_with_history_require_reason_and_keep_safe_audit_values()
    {
        list($user,$market,$section) = $this->fixtures();
        $data = $this->stallData($market,$section)+['location_type'=>'inner'];
        $stall = Stall::create($data);
        $tenant = Tenant::create(['reference'=>'SYN-T','name'=>'Synthetic tenant','status'=>'active']);
        StallAssignment::create(['tenant_id'=>$tenant->id,'stall_id'=>$stall->id,'start_date'=>'2020-01-01','end_date'=>'2020-12-31','status'=>'ended','authorized_by'=>$user->id]);
        $changed = array_merge($data,['area'=>'11.00','location_type'=>'outer']);
        $this->put('/manage/stalls/'.$stall->id,$changed)->assertSessionHasErrors();
        $this->assertSame('10.00',(string)$stall->fresh()->area);
        $this->put('/manage/stalls/'.$stall->id,$changed+['change_reason'=>'Verified survey correction'])->assertSessionHasNoErrors();
        $audit = AuditLog::where('action','stalls.updated')->latest('id')->firstOrFail();
        $this->assertSame('inner',$audit->old_values['location_type']);
        $this->assertSame('outer',$audit->new_values['location_type']);
        $this->assertSame('Verified survey correction',$audit->reason);
        $this->assertArrayNotHasKey('name',$audit->new_values);
    }

    public function test_viewer_cannot_classify_or_change_stall_area()
    {
        list($user,$market,$section) = $this->fixtures('LGU Management Viewer');
        $data = $this->stallData($market,$section);
        $stall = Stall::create($data);
        $this->put('/manage/stalls/'.$stall->id,$data+['location_type'=>'outer'])->assertForbidden();
        $this->assertNull($stall->fresh()->location_type);
    }

    public function test_unified_reports_are_registered_with_explicit_privacy_permissions()
    {
        $types = \App\Services\Reports::TYPES;
        $this->assertArrayHasKey('business-categories',$types);
        $this->assertSame('rates.view',$types['business-categories'][1]);
        $this->assertSame('reports.financial',$types['rental-assessments'][1]);
        $this->assertSame('contracts.view',$types['lease-contract-registry'][1]);
        $this->assertSame('rentals.override.report',$types['rental-overrides'][1]);
        $this->assertCount(20,$types);
    }

    public function test_map_location_filters_and_authorized_business_details_preserve_viewer_privacy()
    {
        list($user,$market,$section) = $this->fixtures();
        $stall = Stall::create($this->stallData($market,$section)+['location_type'=>'outer']);
        $stall->update(['status'=>'occupied']);
        $tenant = Tenant::create(['reference'=>'SYN-T','name'=>'Confidential Synthetic Stallholder','status'=>'active']);
        $category = \App\Models\BusinessCategory::create(['code'=>'SYN-FRUIT','name'=>'Synthetic Fruits','is_active'=>true]);
        $business = \App\Models\Business::create(['tenant_id'=>$tenant->id,'business_category_id'=>$category->id,'name'=>'Synthetic Fruit Shop','status'=>'active']);
        StallAssignment::create(['tenant_id'=>$tenant->id,'business_id'=>$business->id,'stall_id'=>$stall->id,'active_stall_id'=>$stall->id,'start_date'=>'2020-01-01','status'=>'active','authorized_by'=>$user->id]);
        $this->get('/map?location_type=inner')->assertOk()->assertDontSee('SYN-01');
        $this->get('/map?location_type=outer&business_category_id='.$category->id)->assertOk()->assertSee('SYN-01');
        $this->get('/map/stalls/'.$stall->id)->assertOk()->assertSee('Outer')->assertSee('Synthetic Fruits')->assertSee('Synthetic Fruit Shop');
        $user->syncRoles(['LGU Management Viewer']);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->get('/map/stalls/'.$stall->id)->assertOk()->assertDontSee('Confidential Synthetic Stallholder')->assertDontSee('Synthetic Fruit Shop');
        $this->get('/map?business_category_id='.$category->id)->assertForbidden();
        $this->get('/manage/stalls?business_category_id='.$category->id)->assertForbidden();
        $this->get('/map')->assertOk()->assertDontSee('name="business_category_id"',false);
    }

    public function test_new_reports_execute_and_exports_do_not_expose_private_identification()
    {
        list($user,$market) = $this->fixtures();
        foreach (['business-categories','rental-assessments','lease-contract-registry'] as $type) {
            $this->get('/reports/'.$type)->assertOk();
            $response = $this->get('/reports/'.$type.'?format=csv')->assertOk();
            $this->assertStringNotContainsString('government_id',$response->streamedContent());
        }
        $this->get('/reports/rental-assessments?location_type=invalid')->assertSessionHasErrors('location_type');
        $this->get('/reports/business-categories?market_id='.$market->id)->assertSessionHasErrors('market_id');
        $this->get('/reports/rental-assessments?recorded_by='.$user->id)->assertSessionHasErrors('recorded_by');
        $this->get('/reports/business-categories')->assertOk()->assertDontSee('name="market_id"',false)->assertDontSee('name="recorded_by"',false);
    }

    public function test_legacy_business_urls_redirect_and_mutations_keep_existing_fields_and_permissions()
    {
        $this->fixtures();
        $tenant = Tenant::create(['reference'=>'COMPAT','name'=>'Synthetic compatibility tenant','status'=>'active']);
        $this->get('/manage/businesses')->assertRedirect(route('tenant-businesses.index'));
        $this->post('/manage/businesses',['tenant_id'=>$tenant->id,'name'=>'Legacy client business','category'=>'Unmapped legacy text','permit_reference'=>'SYN-PERMIT','registration_reference'=>'SYN-REG','status'=>'active'])->assertSessionHasNoErrors();
        $business = \App\Models\Business::where('tenant_id',$tenant->id)->sole();
        $profile = route('records.show',['type'=>'tenants','id'=>$tenant->id,'tab'=>'businesses']);
        $this->get('/manage/businesses/'.$business->id)->assertRedirect($profile);
        $this->get('/manage/businesses/'.$business->id.'/edit')->assertRedirect(route('tenant-businesses.edit',[$tenant,$business]));
        $this->put('/manage/businesses/'.$business->id,['tenant_id'=>$tenant->id,'name'=>'Updated by compatibility client','status'=>'active'])->assertRedirect($profile);
        $this->assertSame('SYN-PERMIT',$business->fresh()->permit_reference);
        $this->assertSame('Unmapped legacy text',$business->fresh()->category);
        $this->assertNull($business->fresh()->business_category_id);
        auth()->user()->syncRoles(['LGU Management Viewer']);
        auth()->user()->unsetRelation('roles')->unsetRelation('permissions');
        $this->get('/manage/businesses')->assertForbidden();
        $this->put('/manage/businesses/'.$business->id,['tenant_id'=>$tenant->id,'name'=>'Unauthorized change','status'=>'active'])->assertForbidden();
    }

    public function test_stall_details_and_assessment_exports_distinguish_current_category_from_frozen_rent()
    {
        list($user,$market,$section) = $this->fixtures();
        $data = $this->stallData($market,$section)+['location_type'=>'inner'];
        $stall = Stall::create($data);
        $tenant = Tenant::create(['reference'=>'HIST','name'=>'Synthetic history tenant','status'=>'active']);
        $business = $this->businessWithApprovedRental($user,$tenant,$market->id);
        $assignment = app(\App\Services\AssignmentService::class)->assign($user,[
            'tenant_id'=>$tenant->id,'business_id'=>$business->id,'stall_id'=>$stall->id,
            'start_date'=>today()->startOfMonth()->toDateString(),'agreement_reference'=>'HIST-AG',
        ]);
        $assessment = $assignment->agreement->assessment;
        $before = $assessment->getRawOriginal();
        $replacement = \App\Models\BusinessCategory::create(['code'=>'REPLACEMENT','name'=>'Replacement current category']);
        $business->update(['business_category_id'=>$replacement->id]);
        $this->put('/manage/stalls/'.$stall->id,array_merge($data,['status'=>'occupied','area'=>'12.00','location_type'=>'outer','change_reason'=>'Verified updated survey']))->assertSessionHasNoErrors();
        $this->get('/manage/stalls/'.$stall->id)->assertOk()->assertSee('Current business category')->assertSee('Replacement current category')->assertSee('Assessed business category')->assertSee('Fictional shop category')->assertSee('200.00');
        $csv = $this->get('/reports/rental-assessments?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Fictional shop category',$csv);
        $this->assertStringNotContainsString('Replacement current category',$csv);
        $this->get('/reports/rental-assessments?format=print')->assertOk()
            ->assertSee('record-detail')->assertSee('Fictional shop category')->assertSee('Rate Schedule');
        $this->assertSame($before,$assessment->fresh()->getRawOriginal());
    }

    public function test_assignment_confirmation_requires_template_compatible_goods_and_preserves_business_drafts()
    {
        list($user,$market,$section) = $this->fixtures();
        $stall = Stall::create($this->stallData($market,$section)+['location_type'=>'inner']);
        $tenant = Tenant::create(['reference'=>'GOODS','name'=>'Synthetic goods tenant','status'=>'active']);
        $business = $this->businessWithApprovedRental($user,$tenant,$market->id);
        $data = ['tenant_id'=>$tenant->id,'business_id'=>$business->id,'stall_id'=>$stall->id,'start_date'=>today()->startOfMonth()->toDateString(),'agreement_reference'=>'GOODS-AG'];
        foreach (['',str_repeat('x',181)] as $goods) {
            $business->update(['permitted_goods'=>$goods]);
            try {
                app(\App\Services\AssignmentService::class)->assign($user,$data);
                $this->fail('Incompatible goods cannot be frozen into a new agreement.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('business_id',$exception->errors());
            }
            $this->assertSame(0,StallAssignment::count());
            $this->assertSame($goods,(string)$business->fresh()->permitted_goods);
        }
        $business->update(['permitted_goods'=>'Verified synthetic vegetables']);
        $assignment = app(\App\Services\AssignmentService::class)->assign($user,$data);
        $this->assertSame('Verified synthetic vegetables',$assignment->agreement->assessment->snapshot['business']['permitted_goods']);
    }
}
