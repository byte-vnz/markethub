<?php
namespace Tests\Feature;

use App\Models\{Business, BusinessCategory, BusinessCategoryRentalRate, Market, MarketSection, RentalPolicy, Stall, Tenant, User};
use App\Services\{AssignmentService, BillingService, RentalCalculationService};
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RentalAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private $actor;
    private $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->actor = User::create(["name" => "Test reviewer", "email" => "assessment@example.test",
            "password" => "unused", "active" => true]);
        $this->actor->assignRole("Super Administrator");
        $this->tenant = Tenant::create(["reference" => "ASSESSMENT-TEST", "name" => "Fictional Tenant",
            "status" => "active", "government_id_number" => "SYNTHETIC-PRIVATE-ID"]);
    }

    private function stall($code, $location, array $policy = [])
    {
        $market = Market::create(["code" => $code, "name" => "Fictional market " . $code, "status" => "active"]);
        $section = MarketSection::create(["market_id" => $market->id, "code" => "A",
            "name" => "Fictional section", "status" => "active"]);
        RentalPolicy::create(array_replace($this->approval(), ["market_id" => $market->id,
            "daily_cusa" => "10.00", "billing_day_rule" => "calendar", "max_concurrent_assignments" => 1,
            "advance_deposit_authorized" => true], $policy));
        return Stall::create(["market_id" => $market->id, "market_section_id" => $section->id,
            "code" => "A-001", "classification" => "standard", "location_type" => $location,
            "area" => "10.00", "status" => "vacant"]);
    }

    private function business($min, $max)
    {
        $category = BusinessCategory::create(["code" => "SYNTHETIC", "name" => "Fictional trade", "is_active" => true]);
        BusinessCategoryRentalRate::create($this->approval() + ["business_category_id" => $category->id,
            "minimum_rate" => $min, "maximum_rate" => $max]);
        return Business::create(["tenant_id" => $this->tenant->id, "business_category_id" => $category->id,
            "name" => "Fictional shop", "permitted_goods" => "Fictional goods", "status" => "active"]);
    }

    private function approval()
    {
        return ["status" => "approved", "effective_from" => "2020-01-01", "reference_document" => "TEST ONLY",
            "created_by" => $this->actor->id, "approved_by" => $this->actor->id, "approved_at" => now()];
    }

    private function assignmentData($stall, $business, $reference)
    {
        return ["stall_id" => $stall->id, "tenant_id" => $this->tenant->id, "business_id" => $business->id,
            "start_date" => "2024-02-01", "agreement_reference" => $reference];
    }

    /** @dataProvider examples */
    public function test_selects_approved_category_rate_from_verified_location($location, $area, $min, $max, $expected)
    {
        $stall = $this->stall("M1", $location);
        $stall->update(["area" => $area]);
        $result = app(RentalCalculationService::class)
            ->resolve($this->business($min, $max), $stall, "2024-02-01", "2024-02");
        $this->assertSame($expected, $result["daily_rental"]);
        $this->assertSame(29, $result["billing_days"]);
        $this->assertDatabaseCount("rental_assessments", 0);
    }

    public function examples()
    {
        return [
            ["inner", "10.00", "21.04", "26.88", "210.40"],
            ["outer", "10.00", "21.04", "26.88", "268.80"],
            ["inner", "5.00", "34.84", "40.00", "174.20"],
            ["outer", "5.00", "34.84", "40.00", "200.00"],
            ["inner", "10.00", "30.97", "30.97", "309.70"],
            ["outer", "10.00", "30.97", "30.97", "309.70"],
            ["inner", "0.15", "0.10", "0.20", "0.02"],
        ];
    }

    /** @dataProvider invalidApprovals */
    public function test_refuses_unverified_or_ambiguous_basis($obstacle)
    {
        $stall = $this->stall("M1", $obstacle === "location_type" ? null : "inner");
        $business = $this->business("21.04", "26.88");
        if ($obstacle === "category_rate") {
            BusinessCategoryRentalRate::query()->update(["status" => "draft"]);
        }
        if ($obstacle === "rental_policy") {
            RentalPolicy::create($this->approval() + ["market_id" => $stall->market_id,
                "daily_cusa" => "0.00", "billing_day_rule" => "fixed", "fixed_billing_days" => 30,
                "max_concurrent_assignments" => 2, "advance_deposit_authorized" => true]);
        }
        try {
            app(RentalCalculationService::class)->resolve($business, $stall, "2024-02-01", "2024-02");
            $this->fail("Unverified calculation used an ambiguous or incomplete configuration.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($obstacle, $exception->errors());
        }
    }

    public function invalidApprovals()
    {
        return [["location_type"], ["category_rate"], ["rental_policy"]];
    }

    public function test_billing_uses_frozen_basis_and_period_specific_days()
    {
        $stall = $this->stall("M1", "inner");
        $business = $this->business("21.04", "26.88");
        $assignment = app(AssignmentService::class)->assign($this->actor, $this->assignmentData($stall, $business, "NEW"));
        $agreement = $assignment->agreement;
        $this->assertNull($agreement->rental_rate_id);
        $this->assertSame("2024-02", $agreement->assessment->period);
        $this->assertSame("6391.60", $agreement->assessment->total_monthly_rental_cusa);
        $this->assertStringNotContainsString("SYNTHETIC-PRIVATE-ID", $agreement->assessment->getRawOriginal("snapshot"));
        $this->assertArrayNotHasKey("snapshot", $agreement->assessment->toArray());
        $stall->update(["location_type" => "outer", "area" => "50.00", "code" => "RENAMED"]);
        $business->update(["permitted_goods" => "Edited trade"]);
        RentalPolicy::query()->update(["daily_cusa" => "99.00"]);
        $billing = app(BillingService::class);
        $bill = $billing->generate($this->actor, $assignment, "2024-03", "2024-03-31");
        $this->assertSame("draft", $bill->status);
        $this->assertSame("6832.40", $bill->amount);
        $this->assertSame(31, $bill->assessment->billing_days);
        $this->assertSame("A-001", $bill->assessment->stall_code);
        $this->assertSame("Fictional goods", $bill->assessment->snapshot["business"]["permitted_goods"]);
        $this->assertEquals(["6522.40", "310.00"], $bill->items()->pluck("amount")->all());
        $this->assertSame($bill->id, $billing->generate($this->actor, $assignment, "2024-03", "2024-03-31")->id);
        $this->assertDatabaseCount("rental_assessments", 2);
    }

    public function test_fixed_days_zero_cusa_and_inclusive_retirement_date()
    {
        $stall = $this->stall("M1", "inner", [
            "daily_cusa" => "0.00", "billing_day_rule" => "fixed", "fixed_billing_days" => 30,
        ]);
        $business = $this->business("21.04", "26.88");
        BusinessCategoryRentalRate::query()->update(["status" => "retired", "effective_until" => "2024-02-01"]);
        $result = app(RentalCalculationService::class)->resolve($business, $stall, "2024-02-01", "2024-02");
        $this->assertSame(30, $result["billing_days"]);
        $this->assertSame("0.00", $result["monthly_cusa"]);
        $this->assertSame("6312.00", $result["total_monthly_rental_cusa"]);

        $this->expectException(ValidationException::class);
        app(RentalCalculationService::class)->resolve($business, $stall, "2024-02-02", "2024-02");
    }

    public function test_assignment_rejects_manual_rate_and_ignores_amount_overrides()
    {
        $stall = $this->stall("M1", "inner");
        $business = $this->business("21.04", "26.88");
        $input = $this->assignmentData($stall, $business, "NEW");
        $this->actingAs($this->actor)->post("/assignments", $input + ["rental_rate_id" => 123])
            ->assertSessionHasErrors("rental_rate_id");
        $this->assertDatabaseCount("stall_assignments", 0);
        $assignment = app(AssignmentService::class)->assign($this->actor, $input + [
            "floor_area" => "1.00", "location_type" => "outer", "daily_cusa" => "0.00", "total_monthly_rental_cusa" => "0.01",
        ]);
        $this->assertSame("6391.60", $assignment->agreement->monthly_amount);
    }

    public function test_assessment_lookup_cannot_bypass_authorization()
    {
        $stall = $this->stall("M1", "inner");
        $business = $this->business("21.04", "26.88");
        $assignment = app(AssignmentService::class)->assign($this->actor, $this->assignmentData($stall, $business, "NEW"));
        $viewer = User::create(["name" => "Test viewer", "email" => "assessment-viewer@example.test",
            "password" => "unused", "active" => true]);
        $this->expectException(AuthorizationException::class);
        app(RentalCalculationService::class)->assess($viewer, $assignment, "2024-02");
    }

    public function test_initial_assessment_cannot_be_changed()
    {
        $stall = $this->stall("M1", "inner");
        $business = $this->business("21.04", "26.88");
        $assignment = app(AssignmentService::class)->assign($this->actor, $this->assignmentData($stall, $business, "NEW"));
        $this->expectException(\LogicException::class);
        $assignment->agreement->assessment->update(["daily_cusa" => "0.00"]);
    }

    /** @dataProvider concurrentReferences */
    public function test_concurrent_assignment_checks_both_markets_and_concurrent_authorization($reference)
    {
        $first = $this->stall("M1", "inner", ["max_concurrent_assignments" => 1]);
        $other = $this->stall("M2", "outer", ["max_concurrent_assignments" => 2]);
        $business = $this->business("21.04", "26.88");
        $service = app(AssignmentService::class);
        $service->assign($this->actor, $this->assignmentData($first, $business, "FIRST"));
        $second = $this->assignmentData($other, $business, "SECOND") + [
            "concurrent_authorization_reference" => $reference, "concurrent_authorization_reason" => "Fictional authorization",
        ];
        try {
            $service->assign($this->actor, $second);
            $this->fail("Existing market's lower cap was ignored.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey("assignment", $exception->errors());
        }
        $this->assertDatabaseCount("stall_assignments", 1);
        RentalPolicy::where("market_id", $first->market_id)->update(["max_concurrent_assignments" => 2]);
        try {
            $service->assign($this->actor, array_replace($second, ['concurrent_authorization_reference'=>null, 'concurrent_authorization_reason'=>null]));
            $this->fail('Concurrent occupancy without a reason was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment', $exception->errors());
        }
        $result = $service->assign($this->actor, $second);
        $this->assertSame($reference, $result->concurrent_authorization_reference);
        $this->assertSame("268.80", $result->agreement->assessment->daily_rental);
    }

    public function concurrentReferences(): array
    {
        return [['TEST-REVIEW'], [null]];
    }
}
