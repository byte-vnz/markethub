<?php
namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

class DefaultUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $password;

    protected function setUp(): void
    {
        parent::setUp();
        $this->password = Str::random(32) . "aA7!";
        config([
            "default_admin.enabled" => true,
            "default_admin.name" => "System Administrator",
            "default_admin.email" => "admin@marketstall.local",
            "default_admin.password" => $this->password,
        ]);
    }

    private function runSeeder($class = "DefaultUserSeeder")
    {
        $this->withoutMockingConsoleOutput();
        $options = ["--force" => true];
        if ($class !== "DatabaseSeeder") {
            $options["--class"] = $class;
        }
        $this->assertSame(0, Artisan::call("db:seed", $options));
        $output = Artisan::output();
        $this->assertStringNotContainsString($this->password, $output);
        return $output;
    }

    public function test_individual_seeder_creates_active_hashed_administrator_who_can_login_and_manage_modules()
    {
        $this->runSeeder();
        $user = User::where("email", "admin@marketstall.local")->sole();
        $this->assertSame("System Administrator", $user->name);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check($this->password, $user->password));
        $this->assertTrue($user->hasRole("Super Administrator"));
        $this->post("/login", [
            "email" => $user->email,
            "password" => $this->password,
        ])->assertRedirect("/dashboard");
        $this->assertAuthenticatedAs($user);
        foreach (
            [
                "/dashboard",
                "/map",
                "/manage/markets",
                "/manage/sections",
                "/manage/stalls",
                "/manage/tenants",
                "/manage/businesses",
                "/manage/rates",
                "/assignments",
                "/billing",
                "/payments",
                "/reports",
                "/audit",
                "/staff",
                "/settings",
            ]
            as $url
        ) {
            if ($url === '/manage/businesses') {
                $this->get($url)->assertRedirect(route('tenant-businesses.index'));
            } else {
                $this->get($url)->assertOk();
            }
        }
        $this->assertTrue($user->can("staff.manage"));
        $this->assertTrue($user->can("billing.adjust"));
        $this->assertTrue($user->can("payments.reverse"));
    }

    public function test_default_database_seeder_registers_account_and_repeated_runs_preserve_all_account_data()
    {
        $this->runSeeder("DatabaseSeeder");
        $user = User::where("email", "admin@marketstall.local")->firstOrFail();
        $user->update([
            "name" => "Existing administrator",
            "active" => false,
            "remember_token" => Str::random(60),
        ]);
        $user->assignRole("Market Staff");
        $before = $user->fresh()->getRawOriginal();
        $roles = $user->fresh()->getRoleNames()->sort()->values()->all();
        config([
            "default_admin.password" => Str::random(32) . "bB8!",
            "default_admin.name" => "Replacement name",
        ]);
        $this->runSeeder("DatabaseSeeder");
        $this->runSeeder();
        $this->assertSame(1, User::count());
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertSame($roles, $user->fresh()->getRoleNames()->sort()->values()->all());
    }

    public function test_existing_non_administrator_is_warned_and_never_promoted_or_changed()
    {
        $this->seed(RoleSeeder::class);
        $user = User::create([
            "name" => "Existing staff",
            "email" => "ADMIN@marketstall.local",
            "password" => Hash::make(Str::random(40)),
            "active" => false,
        ]);
        $user->assignRole("Market Staff");
        $before = $user->fresh()->getRawOriginal();
        config(["default_admin.password" => null]);
        $output = $this->runSeeder();
        $this->assertStringContainsString("non-administrator", $output);
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertSame(["Market Staff"], $user->fresh()->getRoleNames()->all());
        $this->assertSame(1, User::count());
    }

    public function test_disabled_flag_creates_no_account_but_normal_database_seeding_keeps_roles()
    {
        config(["default_admin.enabled" => false]);
        $this->runSeeder("DatabaseSeeder");
        $this->assertSame(0, User::count());
        $this->assertDatabaseHas("roles", ["name" => "Super Administrator"]);
    }

    /** @dataProvider invalidConfiguration */
    public function test_invalid_configuration_never_creates_account($key, $value)
    {
        config(["default_admin." . $key => $value]);
        $output = $this->runSeeder();
        $this->assertStringContainsString("configuration", $output);
        $this->assertSame(0, User::count());
    }

    public function invalidConfiguration()
    {
        return [
            ["password", null],
            ["password", ""],
            ["password", "short"],
            ["password", str_repeat("a", 20)],
            ["password", str_repeat("A", 20) . "7!"],
            ["password", str_repeat("a", 20) . "A!"],
            ["password", str_repeat("a", 20) . "A7"],
            ["password", str_repeat("a", 72) . "A7!"],
            ["password", "longEnoughAa7!" . chr(0) . "secret"],
            ["email", "not-an-email"],
            ["email", null],
            ["name", "   "],
        ];
    }

    /** @dataProvider blockedEnvironments */
    public function test_creation_is_blocked_outside_local_and_testing_even_with_force($environment)
    {
        $this->app->instance("env", $environment);
        $output = $this->runSeeder();
        $this->assertStringContainsString("local/testing", $output);
        $this->assertSame(0, User::count());
    }

    public function blockedEnvironments()
    {
        return [["production"], ["staging"]];
    }

    public function test_local_environment_and_custom_identity_are_supported()
    {
        $this->app->instance("env", "local");
        config([
            "default_admin.name" => "Local operator",
            "default_admin.email" => "operator@example.test",
        ]);
        $this->runSeeder();
        $this->assertDatabaseHas("users", [
            "name" => "Local operator",
            "email" => "operator@example.test",
            "active" => true,
        ]);
    }
}
