<?php
namespace Tests\Feature;

use App\Models\{User, Market, MarketSection, Stall, Tenant, Business, StallAssignment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TenantReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_is_read_only_aggregate_only_and_identifies_unresolved_records()
    {
        $this->withoutMockingConsoleOutput();
        $user = User::create(['name'=>'Synthetic operator','email'=>'reconcile@example.test','password'=>bcrypt('fixture-only'),'active'=>true]);
        $market = Market::create(['code'=>'REC','name'=>'Synthetic market']);
        $section = MarketSection::create(['market_id'=>$market->id,'code'=>'A','name'=>'Section']);
        $stall = Stall::create(['market_id'=>$market->id,'market_section_id'=>$section->id,'code'=>'REC-01','classification'=>'legacy','area'=>'10.00','status'=>'occupied']);
        $tenant = Tenant::create(['reference'=>'REC-1','name'=>'Private Duplicate Synthetic','phone'=>'09000000000','status'=>'active']);
        $other = Tenant::create(['reference'=>'REC-2','name'=>'Private Duplicate Synthetic','phone'=>'09000000000','status'=>'active']);
        $business = Business::create(['tenant_id'=>$other->id,'name'=>'Private Synthetic Business','category'=>'Unmapped value','status'=>'active']);
        StallAssignment::create(['tenant_id'=>$tenant->id,'business_id'=>$business->id,'stall_id'=>$stall->id,'active_stall_id'=>$stall->id,'start_date'=>'2020-01-01','status'=>'active','authorized_by'=>$user->id]);
        $before = [$tenant->fresh()->getRawOriginal(),$other->fresh()->getRawOriginal(),$business->fresh()->getRawOriginal(),$stall->fresh()->getRawOriginal()];
        $this->assertSame(0,Artisan::call('tenants:reconcile'));
        $output = Artisan::output();
        $this->assertJson($output);
        $report = json_decode($output,true,512,JSON_THROW_ON_ERROR);
        $this->assertSame(2,$report['counts']['tenants']);
        $this->assertSame(1,$report['relationships']['business_assignment_owner_mismatches']);
        $this->assertSame(1,$report['relationships']['potential_duplicate_name_groups']);
        $this->assertSame(1,$report['relationships']['unclassified_stalls']);
        $this->assertSame(1,$report['relationships']['unmapped_businesses']);
        $this->assertSame('0.00',$report['financial_controls']['posted_bill_amount']);
        $this->assertStringNotContainsString('Private Duplicate Synthetic',$output);
        $this->assertStringNotContainsString('Private Synthetic Business',$output);
        $this->assertSame($before,[$tenant->fresh()->getRawOriginal(),$other->fresh()->getRawOriginal(),$business->fresh()->getRawOriginal(),$stall->fresh()->getRawOriginal()]);
    }
}
