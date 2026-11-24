<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
class FoundationTest extends TestCase
{
    use RefreshDatabase;
    public function test_login_screen_is_available_and_dashboard_is_protected()
    {
        $this->get("/login")->assertOk()->assertSee("Sign in");
        $this->get("/dashboard")->assertRedirect("/login");
    }
    public function test_normalized_ledger_schema_exists()
    {
        foreach (
            [
                "markets",
                "market_sections",
                "stalls",
                "tenants",
                "businesses",
                "stall_assignments",
                "rental_rates",
                "rental_agreements",
                "rental_bills",
                "payments",
                "payment_allocations",
                "payment_reversals",
                "audit_logs",
            ]
            as $table
        ) {
            $this->assertTrue(Schema::hasTable($table), $table . " must exist");
        }
    }
}
