<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class UnifiedMigrationTest extends TestCase
{
    public function test_additive_upgrade_preserves_populated_business_assignment_agreement_and_bill_relationships()
    {
        config(['database.connections.unified_upgrade'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
        DB::setDefaultConnection('unified_upgrade');
        $migrations = [
            '2014_10_12_000000_create_users_table.php'=>'CreateUsersTable',
            '2026_11_17_000000_create_permission_tables.php'=>'CreatePermissionTables',
            '2026_11_17_000001_create_market_schema.php'=>'CreateMarketSchema',
            '2026_11_18_000001_enhance_tenant_profiles.php'=>'EnhanceTenantProfiles',
            '2026_11_18_000002_create_lease_contracts.php'=>'CreateLeaseContracts',
        ];
        foreach ($migrations as $file=>$class) {
            require_once database_path('migrations/'.$file);
            (new $class)->up();
        }
        DB::table('users')->insert(['id'=>1,'name'=>'Synthetic operator','email'=>'upgrade@example.test','password'=>'unused-in-migration-test','active'=>true]);
        DB::table('markets')->insert(['id'=>1,'code'=>'UP','name'=>'Synthetic upgrade market']);
        DB::table('market_sections')->insert(['id'=>1,'market_id'=>1,'code'=>'A','name'=>'Section']);
        DB::table('stalls')->insert(['id'=>1,'market_id'=>1,'market_section_id'=>1,'code'=>'UP-001','classification'=>'legacy','area'=>'10.00','status'=>'occupied']);
        DB::table('tenants')->insert(['id'=>1,'reference'=>'UP-T','name'=>'Synthetic upgrade tenant']);
        DB::table('businesses')->insert(['id'=>1,'tenant_id'=>1,'name'=>'Synthetic legacy business','category'=>'Unmapped legacy category','permit_reference'=>'SYN-001']);
        DB::table('rental_rates')->insert(['id'=>1,'market_id'=>1,'classification'=>'legacy','amount'=>'2500.00','effective_from'=>'2020-01-01','approval_reference'=>'SYN-LEGACY']);
        DB::table('stall_assignments')->insert(['id'=>1,'tenant_id'=>1,'business_id'=>1,'stall_id'=>1,'active_stall_id'=>1,'start_date'=>'2020-01-01','authorized_by'=>1]);
        DB::table('rental_agreements')->insert(['id'=>1,'stall_assignment_id'=>1,'rental_rate_id'=>1,'reference'=>'UP-AG','monthly_amount'=>'2500.00','start_date'=>'2020-01-01']);
        DB::table('rental_bills')->insert(['id'=>1,'reference'=>'UP-BILL','stall_assignment_id'=>1,'tenant_id'=>1,'stall_id'=>1,'period'=>'2020-01','due_date'=>'2020-01-31','amount'=>'2500.00','status'=>'posted','created_by'=>1]);
        $before = [];
        foreach (['businesses','stall_assignments','rental_agreements','rental_bills'] as $table) {
            $before[$table] = (array)DB::table($table)->where('id',1)->first();
        }
        foreach (['2026_11_18_000003_unify_business_categories.php'=>'UnifyBusinessCategories','2026_11_18_000004_create_rental_assessments.php'=>'CreateRentalAssessments'] as $file=>$class) {
            require_once database_path('migrations/'.$file);
            (new $class)->up();
        }
        foreach ($before as $table=>$row) {
            $after = (array)DB::table($table)->where('id',1)->first();
            $this->assertSame($row,array_intersect_key($after,$row),$table.' existing values must remain unchanged');
            $this->assertSame(1,DB::table($table)->count());
        }
        $this->assertNull(DB::table('stalls')->value('location_type'));
        $this->assertNull(DB::table('businesses')->value('business_category_id'));
        $this->assertSame([],DB::select('PRAGMA foreign_key_check'));
        $this->assertTrue(Schema::hasColumn('rental_agreements','rental_assessment_id'));
        DB::disconnect('unified_upgrade');
    }
}
