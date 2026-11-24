<?php
namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_asset_is_only_loaded_by_the_dashboard()
    {
        $this->actingAs($this->account('Super Administrator'));
        $this->get('/dashboard')->assertOk()->assertSee('/assets/chart.js', false);
        $this->get('/settings')->assertOk()->assertDontSee('/assets/chart.js', false);
    }

    protected function account($role)
    {
        $this->seed(RoleSeeder::class);
        $user = User::create([
            "name" => "Navigation fixture",
            "email" => strtolower(str_replace(" ", "", $role)) . "@example.test",
            "password" => Hash::make("fixture-only"),
            "active" => true,
        ]);
        $user->assignRole($role);
        return $user;
    }

    public function test_administrator_sees_every_existing_menu_group_and_functional_links()
    {
        $this->actingAs($this->account("Super Administrator"));
        $response = $this->get("/dashboard")->assertOk();
        foreach (
            ["dashboard", "management", "billing", "reports", "administration", "settings"]
            as $group
        ) {
            $response->assertSee('data-nav-group="' . $group . '"', false);
        }
        foreach (config("navigation") as $group) {
            foreach ($group["items"] as $item) {
                $response->assertSee(
                    'href="' . route($item["route"], $item["params"]) . '"',
                    false,
                );
            }
        }
        $response->assertSee('aria-current="page"', false);
        $response->assertSee('action="' . route("logout") . '"', false);
    }

    public function test_viewer_sees_only_permitted_links_and_hidden_categories()
    {
        $this->actingAs($this->account("LGU Management Viewer"));
        $response = $this->get("/dashboard")->assertOk();
        $response->assertSee('data-nav-group="management"', false);
        $response->assertSee('data-nav-group="reports"', false);
        $response->assertDontSee('data-nav-group="billing"', false);
        $response->assertDontSee('data-nav-group="administration"', false);
        $response->assertSee('data-nav-group="settings"', false);
        $response->assertDontSee('href="' . route("settings") . '"', false);
        $response->assertDontSee('href="' . route("payments.create") . '"', false);
        $this->get("/staff")->assertForbidden();
    }

    public function test_nested_record_and_report_routes_highlight_only_the_matching_item()
    {
        $this->actingAs($this->account("Super Administrator"));
        $record = $this->get("/manage/stalls/create")->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="[^"]*manage\/stalls"[^>]+aria-current="page"/',
            $record,
        );
        $report = $this->get("/reports/occupied-stalls")->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="[^"]*reports\/occupied-stalls"[^>]+aria-current="page"/',
            $report,
        );
        $this->assertSame(1, substr_count($report, 'aria-current="page"'));
        $other = $this->get("/reports/monthly-billing")->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="[^"]*reports"[^>]+aria-current="page"/',
            $other,
        );
    }
    public function test_staff_menu_respects_collection_and_administration_permissions()
    {
        $this->actingAs($this->account("Market Staff"));
        $response = $this->get("/dashboard")->assertOk();
        $response->assertSee('href="' . route("records.index", ["type" => "tenants"]) . '"', false);
        $response->assertSee('href="' . route("billing.index") . '"', false);
        $response->assertDontSee('href="' . route("payments.create") . '"', false);
        $response->assertDontSee('data-nav-group="administration"', false);
        $this->get("/payments/create")->assertForbidden();
    }
}
