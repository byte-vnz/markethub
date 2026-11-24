<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Fixtures\ApprovedRentalConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\{
    User,
    Market,
    MarketSection,
    Stall,
    Tenant,
    Business,
    RentalRate,
    RentalAgreement,
    RentalBill,
    Payment,
    AuditLog,
    StallAssignment,
};
use App\Services\{AssignmentService, BillingService, PaymentService};
use Database\Seeders\RoleSeeder;
class WorkflowTest extends TestCase
{
    use RefreshDatabase, ApprovedRentalConfiguration;
    protected $admin;
    protected $tenant;
    protected $stall;
    protected $rate;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::create([
            "name" => "Admin",
            "email" => "admin@example.test",
            "password" => Hash::make("test-password"),
            "active" => true,
        ]);
        $this->admin->assignRole("Super Administrator");
        $market = Market::create(["name" => "Test market", "code" => "M1", "status" => "active"]);
        $section = MarketSection::create([
            "market_id" => $market->id,
            "name" => "Produce",
            "code" => "A",
            "status" => "active",
        ]);
        $this->stall = Stall::create([
            "market_id" => $market->id,
            "market_section_id" => $section->id,
            "code" => "A-001",
            "classification" => "standard",
            "area" => "9.00",
            "location_type" => "inner",
            "status" => "vacant",
        ]);
        $this->tenant = Tenant::create([
            "reference" => "T1",
            "name" => "Test Tenant",
            "phone" => "09000000000",
            "address" => "Fictional address",
            "status" => "active",
        ]);
        $this->rate = RentalRate::create([
            "market_id" => $market->id,
            "classification" => "standard",
            "amount" => "2500.00",
            "effective_from" => "2020-01-01",
            "approval_reference" => "TEST",
            "status" => "active",
        ]);
        $this->actingAs($this->admin);
    }
    protected function assignment($start = null, $reference = "AGR-1")
    {
        $start = $start ?? today()->startOfMonth()->toDateString();
        $assignment = StallAssignment::create([
            "stall_id" => $this->stall->id,
            "active_stall_id" => $this->stall->id,
            "tenant_id" => $this->tenant->id,
            "status" => "active",
            "authorized_by" => $this->admin->id,
            "start_date" => $start,
        ]);
        RentalAgreement::create([
            "stall_assignment_id" => $assignment->id,
            "rental_rate_id" => $this->rate->id,
            "start_date" => $start,
            "reference" => $reference,
            "monthly_amount" => $this->rate->amount,
        ]);
        $this->stall->update(["status" => "occupied"]);
        return $assignment;
    }
    protected function bill()
    {
        $a = $this->assignment();
        $b = app(BillingService::class)->generate(
            $this->admin,
            $a,
            now()->format("Y-m"),
            now()->endOfMonth()->toDateString(),
        );
        return app(BillingService::class)->post($this->admin, $b);
    }
    protected function payment($bill, $amount = "1000.00", $key = null)
    {
        return app(PaymentService::class)->createDraft($this->admin, [
            "tenant_id" => $this->tenant->id,
            "payment_date" => today()->toDateString(),
            "amount" => $amount,
            "method" => "cash",
            "receipt_reference" => "TEST-" . uniqid(),
            "idempotency_key" => $key ?: (string) \Illuminate\Support\Str::uuid(),
            "allocations" => [$bill->id => $amount],
        ]);
    }
    public function test_login_logout_and_disabled_users()
    {
        auth()->logout();
        $this->post("/login", [
            "email" => $this->admin->email,
            "password" => "test-password",
        ])->assertRedirect("/dashboard");
        $this->assertAuthenticatedAs($this->admin);
        $this->post("/logout")->assertRedirect("/login");
        $this->assertGuest();
        $this->admin->update(["active" => false]);
        $this->post("/login", [
            "email" => $this->admin->email,
            "password" => "test-password",
        ])->assertSessionHasErrors("email");
        $this->assertGuest();
    }
    public function test_viewer_cannot_read_pii_or_mutate_records()
    {
        $viewer = User::create([
            "name" => "Viewer",
            "email" => "viewer@example.test",
            "password" => "unused",
            "active" => true,
        ]);
        $viewer->assignRole("LGU Management Viewer");
        $this->actingAs($viewer)->get("/dashboard")->assertOk();
        $this->get("/manage/tenants")->assertForbidden();
        $this->post("/manage/stalls", [])->assertForbidden();
        $this->get("/reports/tenant-master")->assertForbidden();
    }
    public function test_assignment_conflict_is_rejected_and_history_kept()
    {
        $business = $this->businessWithApprovedRental($this->admin, $this->tenant, $this->stall->market_id);
        $data = [
            "stall_id" => $this->stall->id, "tenant_id" => $this->tenant->id, "business_id" => $business->id,
            "start_date" => today()->startOfMonth()->toDateString(), "agreement_reference" => "ASSESSMENT",
        ];
        $service = app(AssignmentService::class);
        $a = $service->assign($this->admin, $data);
        try {
            $service->assign($this->admin, array_replace($data, ["agreement_reference" => "CONFLICT"]));
            $this->fail("Duplicate assignment accepted");
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        app(AssignmentService::class)->end(
            $this->admin,
            $a,
            today()->toDateString(),
            "Agreement ended",
        );
        $this->assertDatabaseHas("stall_assignments", [
            "id" => $a->id,
            "status" => "ended",
            "active_stall_id" => null,
        ]);
        $this->assertDatabaseCount("stall_assignments", 1);
    }
    public function test_duplicate_billing_is_idempotent_and_rate_is_snapshotted()
    {
        $a = $this->assignment();
        $svc = app(BillingService::class);
        $b = $svc->generate(
            $this->admin,
            $a,
            now()->format("Y-m"),
            now()->endOfMonth()->toDateString(),
        );
        $same = $svc->generate(
            $this->admin,
            $a,
            now()->format("Y-m"),
            now()->endOfMonth()->toDateString(),
        );
        $this->assertEquals($b->id, $same->id);
        $this->assertEquals("2500.00", $b->amount);
        $this->assertNull($b->rental_assessment_id);
        $this->assertSame(1, $b->items()->count());
        $this->assertEquals("draft", $b->status);
        $this->assertDatabaseCount("rental_bills", 1);
    }
    public function test_partial_full_payment_reversal_and_dashboard_reconcile()
    {
        $b = $this->bill();
        $svc = app(PaymentService::class);
        $p = $this->payment($b);
        $svc->post($this->admin, $p);
        $svc->post($this->admin, $p);
        $this->assertSame("1500.00", $b->fresh()->balance());
        $p2 = $this->payment($b, "1500.00");
        $svc->post($this->admin, $p2);
        $this->assertSame("0.00", $b->fresh()->balance());
        $svc->reverse($this->admin, $p, "Incorrect recorded reference");
        $this->assertSame("1000.00", $b->fresh()->balance());
        $this->assertDatabaseHas("payments", [
            "id" => $p->id,
            "status" => "posted",
            "amount" => "1000.00",
        ]);
        $this->assertDatabaseCount("payment_reversals", 1);
        $this->get("/dashboard")->assertOk();
        $this->assertGreaterThan(3, AuditLog::count());
    }
    public function test_overpayment_and_foreign_tenant_allocations_are_rejected()
    {
        $b = $this->bill();
        try {
            $this->payment($b, "2500.01");
            $this->fail("Overpayment accepted");
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $other = Tenant::create(["reference" => "T2", "name" => "Other", "status" => "active"]);
        $b->tenant_id = $other->id; // Do not mutate the ledger: use a separate tenant in request.
        $this->expectException(ValidationException::class);
        app(PaymentService::class)->createDraft($this->admin, [
            "tenant_id" => $other->id,
            "payment_date" => today()->toDateString(),
            "amount" => "1.00",
            "method" => "cash",
            "receipt_reference" => "WRONG",
            "idempotency_key" => (string) \Illuminate\Support\Str::uuid(),
            "allocations" => [$b->id => "1.00"],
        ]);
    }
    public function test_stale_drafts_cannot_overpay_at_post_time()
    {
        $b = $this->bill();
        $p = $this->payment($b, "2500.00");
        $p2 = $this->payment($b, "2500.00");
        app(PaymentService::class)->post($this->admin, $p);
        $this->expectException(ValidationException::class);
        app(PaymentService::class)->post($this->admin, $p2);
    }
    public function test_financial_service_denies_market_staff()
    {
        $b = $this->bill();
        $this->admin->syncRoles("Market Staff");
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->payment($b);
    }
    public function test_stall_and_tenant_http_crud_and_scope_validation()
    {
        $this->post("/manage/tenants", [
            "reference" => "NEW",
            "name" => "New tenant",
            "status" => "active",
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertDatabaseHas("tenants", ["reference" => "NEW"]);
        $this->put("/manage/tenants/" . $this->tenant->id, [
            "reference" => "T1",
            "name" => "Updated name",
            "status" => "active",
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertDatabaseHas("tenants", ["id" => $this->tenant->id, "name" => "Updated name"]);
        $this->get("/manage/stalls")->assertOk();
        $this->get("/map")->assertOk();
    }
    public function test_csv_xlsx_pdf_exports_are_real_downloads()
    {
        $this->bill();
        $this->get("/reports/monthly-billing?format=csv")
            ->assertOk()
            ->assertHeader("content-type", "text/csv; charset=UTF-8");
        $this->get("/reports/monthly-billing?format=xlsx")->assertOk();
        $this->get("/reports/monthly-billing?format=pdf")
            ->assertOk()
            ->assertHeader("content-type", "application/pdf");
    }

    public function test_adjustment_retry_is_idempotent_and_key_mismatch_is_rejected()
    {
        $b = $this->bill();
        $svc = app(BillingService::class);
        $d = [
            "amount" => "100.00",
            "reason" => "Approved correction",
            "approval_reference" => "ADJ-1",
            "idempotency_key" => (string) \Illuminate\Support\Str::uuid(),
        ];
        $svc->adjust($this->admin, $b, $d);
        $svc->adjust($this->admin, $b, $d);
        $this->assertSame("2600.00", $b->fresh()->balance());
        $this->assertDatabaseCount("billing_adjustments", 1);
        $d["amount"] = "200.00";
        $this->expectException(ValidationException::class);
        $svc->adjust($this->admin, $b, $d);
    }
    public function test_reversed_payment_can_be_replaced_with_the_real_receipt()
    {
        $b = $this->bill();
        $svc = app(PaymentService::class);
        $p = $this->payment($b);
        $svc->post($this->admin, $p);
        $svc->reverse($this->admin, $p, "Incorrect amount recorded");
        $replacement = $svc->createDraft($this->admin, [
            "tenant_id" => $this->tenant->id,
            "replacement_of_id" => $p->id,
            "payment_date" => today()->toDateString(),
            "amount" => "500.00",
            "method" => "cash",
            "receipt_reference" => $p->receipt_reference,
            "idempotency_key" => (string) \Illuminate\Support\Str::uuid(),
            "allocations" => [$b->id => "500.00"],
        ]);
        $svc->post($this->admin, $replacement);
        $this->assertSame("2000.00", $b->fresh()->balance());
        $this->assertEquals($p->receipt_reference, $replacement->receipt_reference);
        $this->assertEquals($p->id, $replacement->replacement_of_id);
    }
    public function test_assignment_cannot_end_midway_through_a_billed_month()
    {
        $b = $this->bill();
        $this->expectException(ValidationException::class);
        app(AssignmentService::class)->end(
            $this->admin,
            $b->assignment,
            now()->startOfMonth()->toDateString(),
            "End requested midmonth",
        );
    }
    public function test_password_reset_invalidates_an_existing_session()
    {
        $this->withSession(["password_hash_web" => $this->admin->password]);
        $this->admin->password = Hash::make("replacement-secret");
        $this->admin->save();
        $this->get("/dashboard")->assertRedirect("/login");
        $this->assertGuest();
    }

    public function test_demo_seed_is_guarded_and_repeatable()
    {
        config(["markethub.demo_password" => "Long-local-demo-secret"]);
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $this->assertEquals(
            48,
            Stall::whereHas("market", function ($q) {
                $q->where("code", "DEMO");
            })->count(),
        );
        $before = Payment::count();
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $this->assertEquals($before, Payment::count());
    }
    public function test_layout_persists_and_overlap_is_rejected()
    {
        $payload = [
            "columns" => 6,
            "rows" => 10,
            "positions" => [
                [
                    "stall_id" => $this->stall->id,
                    "row" => 2,
                    "column" => 3,
                    "width" => 2,
                    "height" => 1,
                ],
            ],
        ];
        $this->post("/map/layout/" . $this->stall->market_id, $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertDatabaseHas("stall_layout_positions", [
            "stall_id" => $this->stall->id,
            "row" => 2,
            "column" => 3,
        ]);
        $payload["positions"][0]["column"] = 6;
        $this->post("/map/layout/" . $this->stall->market_id, $payload)->assertSessionHasErrors();
    }
    public function test_reports_and_dashboard_derive_the_same_reversed_totals()
    {
        $b = $this->bill();
        $svc = app(PaymentService::class);
        $p = $this->payment($b);
        $svc->post($this->admin, $p);
        $d = \App\Services\Ledger::dashboard(
            null,
            today()->startOfMonth()->toDateString(),
            today()->toDateString(),
        );
        $this->assertSame("1000.00", $d["collected"]);
        $this->assertSame("1500.00", $d["outstanding"]);
        $rows = \App\Services\Reports::query("collection-summary", [])->get();
        $this->assertEquals("1000.00", bcadd((string) $rows->first()->collected, "0", 2));
        $svc->reverse($this->admin, $p, "Reconcile duplicate receipt");
        $d = \App\Services\Ledger::dashboard(
            null,
            today()->startOfMonth()->toDateString(),
            today()->toDateString(),
        );
        $this->assertSame("0.00", $d["collected"]);
        $this->assertSame("2500.00", $d["outstanding"]);
    }
    public function test_every_report_and_main_screen_renders()
    {
        $b = $this->bill();
        $p = $this->payment($b);
        app(PaymentService::class)->post($this->admin, $p);
        foreach (
            [
                "/map",
                "/map/stalls/" . $this->stall->id,
                "/map/layout/" . $this->stall->market_id,
                "/assignments",
                "/assignments/create",
                "/assignments/" . $b->stall_assignment_id . "/transfer",
                "/billing",
                "/billing/create",
                "/billing/" . $b->id,
                "/receivables",
                "/payments",
                "/payments/create?tenant_id=" . $this->tenant->id,
                "/payments/" . $p->id,
                "/reports",
                "/staff",
                "/audit",
                "/settings",
                "/manage/tenants/" . $this->tenant->id,
                "/manage/stalls/" . $this->stall->id . "/edit",
            ]
            as $url
        ) {
            $this->get($url)->assertOk();
        }
        foreach (\App\Support\Resources::all() as $type => $spec) {
            if ($type === "businesses") {
                $this->followingRedirects()->get("/manage/businesses")->assertOk();
                $this->followingRedirects()->get("/manage/businesses/create?tenant_id=" . $this->tenant->id)->assertOk();
                continue;
            }
            $this->get("/manage/" . $type)->assertOk();
            $this->get("/manage/" . $type . "/create")->assertOk();
        }
        foreach (\App\Services\Reports::TYPES as $type => $spec) {
            $query = isset(\App\Services\Reports::scopeFilters($type)["tenant_id"]) ? "?tenant_id=" . $this->tenant->id : "";
            $this->get("/reports/" . $type . $query)->assertOk();
        }
    }
    public function test_viewer_map_does_not_disclose_tenant_information()
    {
        $this->assignment();
        $this->admin->syncRoles("LGU Management Viewer");
        $this->get("/map/stalls/" . $this->stall->id)
            ->assertOk()
            ->assertDontSee("Test Tenant")
            ->assertDontSee("09000000000");
    }
    public function test_archiving_occupied_stall_is_rejected()
    {
        $this->assignment();
        $this->put("/manage/stalls/" . $this->stall->id, [
            "market_id" => $this->stall->market_id,
            "market_section_id" => $this->stall->market_section_id,
            "code" => "A-001",
            "classification" => "standard",
            "area" => "9.00",
            "location_type" => "inner",
            "status" => "inactive",
        ])->assertSessionHasErrors("status");
        $this->assertDatabaseHas("stalls", ["id" => $this->stall->id, "status" => "occupied"]);
    }
    public function test_transfer_preserves_history_and_renewal_agreement()
    {
        $start = today()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $a = $this->assignment($start, "OLD");
        $oldBill = app(BillingService::class)->generate($this->admin, $a, substr($start, 0, 7), today()->toDateString());
        app(BillingService::class)->post($this->admin, $oldBill);
        $snapshot = $oldBill->fresh()->getAttributes();
        $business = $this->businessWithApprovedRental($this->admin, $this->tenant, $this->stall->market_id);
        $next = app(AssignmentService::class)->transfer($this->admin, $a, [
            "stall_id" => $this->stall->id,
            "business_id" => $business->id,
            "start_date" => today()->startOfMonth()->toDateString(),
            "agreement_reference" => "NEW",
            "reason" => "Renewal authorization",
        ]);
        $this->assertEquals("ended", $a->fresh()->status);
        $this->assertEquals("active", $next->status);
        $this->assertSame($snapshot, $oldBill->fresh()->getAttributes());
        $this->assertSame("2500.00", $a->agreement->monthly_amount);
        $this->assertSame("5700.00", $next->agreement->monthly_amount);
        $this->assertNotNull($next->agreement->rental_assessment_id);
        $this->assertDatabaseCount("stall_assignments", 2);
    }
    public function test_csv_formula_prefix_is_neutralized()
    {
        foreach (["=SUM(1,1)", "+cmd", "-cmd", "@formula", "\t=evil"] as $value) {
            $this->assertStringStartsWith("'", \App\Services\Reports::csvCell($value));
        }
        $this->assertSame("Safe text", \App\Services\Reports::csvCell("Safe text"));
    }
    public function test_scheduler_command_only_generates_drafts()
    {
        $a = $this->assignment();
        $this->artisan("billing:draft", [
            "period" => today()->format("Y-m"),
            "--due" => today()->endOfMonth()->toDateString(),
            "--actor" => $this->admin->id,
        ])->assertExitCode(0);
        $this->assertDatabaseHas("rental_bills", [
            "stall_assignment_id" => $a->id,
            "status" => "draft",
        ]);
    }

    public function test_full_credit_adjustment_retry_remains_idempotent()
    {
        $b = $this->bill();
        $d = [
            "idempotency_key" => (string) \Illuminate\Support\Str::uuid(),
            "amount" => "-2500.00",
            "reason" => "Approved full bill correction",
            "approval_reference" => "CREDIT-1",
        ];
        $svc = app(BillingService::class);
        $svc->adjust($this->admin, $b, $d);
        $svc->adjust($this->admin, $b, $d);
        $this->assertSame("0.00", $b->fresh()->balance());
        $this->assertDatabaseCount("billing_adjustments", 1);
    }
    public function test_allocation_and_reversal_records_cannot_be_rewritten()
    {
        $b = $this->bill();
        $p = $this->payment($b);
        app(PaymentService::class)->post($this->admin, $p);
        $allocation = $p->allocations()->first();
        $this->expectException(\LogicException::class);
        $allocation->update(["amount" => "1.00"]);
    }
    public function test_collection_officer_cannot_reverse_payment()
    {
        $b = $this->bill();
        $p = $this->payment($b);
        app(PaymentService::class)->post($this->admin, $p);
        $this->admin->syncRoles("Authorized Collection Officer");
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(PaymentService::class)->reverse($this->admin, $p, "Unauthorized correction");
    }
    public function test_posted_bill_cannot_be_silently_edited()
    {
        $b = $this->bill();
        $this->expectException(\LogicException::class);
        $b->update(["amount" => "1.00"]);
    }
    public function test_login_is_rate_limited()
    {
        auth()->logout();
        for ($i = 0; $i < 5; $i++) {
            $this->post("/login", [
                "email" => "blocked@example.test",
                "password" => "wrong",
            ])->assertSessionHasErrors("email");
        }
        $this->post("/login", [
            "email" => "blocked@example.test",
            "password" => "wrong",
        ])->assertSessionHasErrors("email");
        $this->assertStringContainsString("Too many attempts", session("errors")->first("email"));
    }
    public function test_decimal_input_rejects_fractions_beyond_centavos()
    {
        $this->expectException(ValidationException::class);
        \App\Services\Money::positive("1.001");
    }

    public function test_replacement_cannot_borrow_an_unrelated_reversed_receipt()
    {
        $b = $this->bill();
        $svc = app(PaymentService::class);
        $a = $this->payment($b, "100.00");
        $svc->post($this->admin, $a);
        $c = $this->payment($b, "100.00");
        $svc->post($this->admin, $c);
        $svc->reverse($this->admin, $a, "First correction");
        $svc->reverse($this->admin, $c, "Second correction");
        $this->expectException(ValidationException::class);
        $svc->createDraft($this->admin, [
            "tenant_id" => $this->tenant->id,
            "replacement_of_id" => $a->id,
            "payment_date" => today()->toDateString(),
            "amount" => "100.00",
            "method" => "cash",
            "receipt_reference" => $c->receipt_reference,
            "idempotency_key" => (string) \Illuminate\Support\Str::uuid(),
            "allocations" => [$b->id => "100.00"],
        ]);
    }

    public function test_mistaken_draft_can_be_voided_and_replaced_without_posting()
    {
        $b = $this->bill();
        $svc = app(PaymentService::class);
        $p = $this->payment($b);
        $svc->cancelDraft($this->admin, $p, "Incorrect draft allocation");
        $this->assertSame("2500.00", $b->fresh()->balance());
        $this->assertSame("void", $p->fresh()->status);
        $new = $svc->createDraft($this->admin, [
            "tenant_id" => $this->tenant->id,
            "replacement_of_id" => $p->id,
            "payment_date" => today()->toDateString(),
            "amount" => "500.00",
            "method" => "cash",
            "receipt_reference" => $p->receipt_reference,
            "idempotency_key" => (string) \Illuminate\Support\Str::uuid(),
            "allocations" => [$b->id => "500.00"],
        ]);
        $svc->post($this->admin, $new);
        $this->assertSame("2000.00", $b->fresh()->balance());
    }
}
