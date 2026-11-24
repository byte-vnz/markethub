<?php
namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class UnifiedProvisioningSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_reseeding_preserves_custom_grants_and_deliberate_revocations()
    {
        $this->seed(RoleSeeder::class);
        $role = Role::findByName('Super Administrator','web');
        $role->givePermissionTo(Permission::findOrCreate('custom.existing','web'));
        $role->revokePermissionTo(['staff.manage','rates.approve']);
        $before = $role->fresh()->permissions()->pluck('name')->sort()->values()->all();
        $this->seed(RoleSeeder::class);
        $this->assertSame($before,$role->fresh()->permissions()->pluck('name')->sort()->values()->all());
        $this->seed(RoleSeeder::class);
        $this->assertSame($before,$role->fresh()->permissions()->pluck('name')->sort()->values()->all());
    }

    public function test_submission_rollback_guard_retains_persisted_idempotency_keys()
    {
        $actor = \App\Models\User::create(['name'=>'Synthetic provisioner','email'=>'provisioner@example.test','password'=>bcrypt('fixture-only'),'active'=>true]);
        $tenant = \App\Models\Tenant::create(['reference'=>'KEY-TENANT','name'=>'Synthetic keyed tenant','status'=>'draft']);
        DB::table('tenant_registration_submissions')->insert([
            'submission_key'=>(string)\Illuminate\Support\Str::uuid(),'actor_id'=>$actor->id,'tenant_id'=>$tenant->id,'request_hash'=>str_repeat('a',64),'created_at'=>now(),
        ]);
        require_once database_path('migrations/2026_11_18_000005_create_tenant_registration_submissions.php');
        try {
            (new \CreateTenantRegistrationSubmissions)->down();
            $this->fail('A populated idempotency ledger must block rollback.');
        } catch (\LogicException $exception) {
            $this->assertTrue(Schema::hasTable('tenant_registration_submissions'));
            $this->assertSame(1,DB::table('tenant_registration_submissions')->count());
        }
    }

    public function test_interrupted_permission_upgrade_rolls_back_and_retry_grants_only_the_new_authority()
    {
        $this->seed(RoleSeeder::class);
        $role = Role::findByName('Super Administrator','web');
        $role->givePermissionTo(Permission::findOrCreate('custom.existing','web'));
        $role->revokePermissionTo('staff.manage');
        Permission::findByName('rates.approve','web')->delete();
        $interrupt = true;
        DB::listen(function ($query) use (&$interrupt) {
            if ($interrupt && strpos(strtolower($query->sql),'insert into') !== false && strpos($query->sql,'permissions') !== false && in_array('rates.approve',$query->bindings,true)) {
                $interrupt = false;
                throw new \RuntimeException('Synthetic interrupted permission upgrade');
            }
        });
        try {
            (new RoleSeeder)->run();
            $this->fail('The synthetic interruption must be exercised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic interrupted permission upgrade',$exception->getMessage());
        }
        $this->assertDatabaseMissing('permissions',['name'=>'rates.approve','guard_name'=>'web']);
        (new RoleSeeder)->run();
        $names = $role->fresh()->permissions()->pluck('name')->all();
        $this->assertContains('rates.approve',$names);
        $this->assertContains('custom.existing',$names);
        $this->assertNotContains('staff.manage',$names);
    }
}
