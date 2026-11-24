<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateMarketSchema extends Migration
{
    public function up()
    {
        Schema::table("users", function (Blueprint $t) {
            $t->boolean("active")->default(true);
        });
        Schema::create("markets", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->string("code", 40)->unique();
            $t->string("name");
            $t->string("address")->nullable();
            $t->string("status", 20)->default("active")->index();
            $t->timestamps();
        });
        Schema::create("market_sections", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("market_id")->constrained();
            $t->string("code", 40);
            $t->string("name");
            $t->string("status", 20)->default("active");
            $t->unique(["market_id", "code"]);
            $t->timestamps();
        });
        Schema::create("market_layouts", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("market_id")->unique()->constrained();
            $t->string("name");
            $t->unsignedInteger("columns")->default(12);
            $t->unsignedInteger("rows")->default(20);
            $t->timestamps();
        });
        Schema::create("stall_categories", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->string("name")->unique();
            $t->timestamps();
        });
        Schema::create("stalls", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("market_id")->constrained();
            $t->foreignId("market_section_id")->constrained();
            $t->foreignId("stall_category_id")->nullable()->constrained();
            $t->string("code", 40);
            $t->string("floor")->nullable();
            $t->string("classification", 80);
            $t->decimal("area", 10, 2)->default(0);
            $t->string("status", 20)->default("vacant")->index();
            $t->text("description")->nullable();
            $t->text("remarks")->nullable();
            $t->foreignId("created_by")->nullable()->constrained("users");
            $t->foreignId("updated_by")->nullable()->constrained("users");
            $t->unique(["market_id", "code"]);
            $t->timestamps();
        });
        Schema::create("stall_layout_positions", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("market_layout_id")->constrained();
            $t->foreignId("stall_id")->unique()->constrained();
            $t->unsignedInteger("row");
            $t->unsignedInteger("column");
            $t->unsignedInteger("width")->default(1);
            $t->unsignedInteger("height")->default(1);
            $t->timestamps();
        });
        Schema::create("tenants", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->string("reference", 60)->unique();
            $t->string("name")->index();
            $t->string("phone", 40)->nullable();
            $t->string("email")->nullable();
            $t->text("address")->nullable();
            $t->string("status", 20)->default("active")->index();
            $t->text("remarks")->nullable();
            $t->timestamps();
        });
        Schema::create("businesses", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("tenant_id")->constrained();
            $t->string("name");
            $t->string("category")->nullable();
            $t->text("description")->nullable();
            $t->string("permit_reference")->nullable();
            $t->string("registration_reference")->nullable();
            $t->date("permit_from")->nullable();
            $t->date("permit_until")->nullable();
            $t->string("status", 20)->default("active");
            $t->timestamps();
        });
        Schema::create("rental_rates", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("market_id")->constrained();
            $t->string("classification", 80);
            $t->decimal("amount", 14, 2);
            $t->date("effective_from");
            $t->date("effective_until")->nullable();
            $t->string("approval_reference");
            $t->string("status", 20)->default("active");
            $t->index(["market_id", "classification", "effective_from"]);
            $t->timestamps();
        });
        Schema::create("stall_assignments", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("stall_id")->constrained();
            $t->foreignId("tenant_id")->constrained();
            $t->foreignId("business_id")->nullable()->constrained();
            $t->foreignId("active_stall_id")->nullable()->unique()->constrained("stalls");
            $t->date("start_date");
            $t->date("end_date")->nullable();
            $t->string("status", 20)->default("active");
            $t->text("remarks")->nullable();
            $t->foreignId("authorized_by")->constrained("users");
            $t->index(["stall_id", "start_date", "end_date"]);
            $t->timestamps();
        });
        Schema::create("rental_agreements", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("stall_assignment_id")->unique()->constrained();
            $t->foreignId("rental_rate_id")->constrained();
            $t->string("reference")->unique();
            $t->decimal("monthly_amount", 14, 2);
            $t->date("start_date");
            $t->date("end_date")->nullable();
            $t->timestamps();
        });
        Schema::create("rental_bills", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->string("reference", 60)->unique();
            $t->foreignId("stall_assignment_id")->constrained();
            $t->foreignId("tenant_id")->constrained();
            $t->foreignId("stall_id")->constrained();
            $t->string("period", 7);
            $t->date("due_date")->index();
            $t->decimal("amount", 14, 2);
            $t->string("status", 20)->default("draft")->index();
            $t->foreignId("created_by")->constrained("users");
            $t->foreignId("posted_by")->nullable()->constrained("users");
            $t->timestamp("posted_at")->nullable();
            $t->unique(["stall_assignment_id", "period"]);
            $t->timestamps();
        });
        Schema::create("rental_bill_items", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("rental_bill_id")->constrained();
            $t->string("description");
            $t->decimal("amount", 14, 2);
            $t->timestamps();
        });
        Schema::create("payments", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->string("reference", 60)->unique();
            $t->foreignId("tenant_id")->constrained();
            $t->date("payment_date")->index();
            $t->decimal("amount", 14, 2);
            $t->string("method", 30);
            $t->string("receipt_reference", 120)->index();
            $t->string("active_receipt_reference", 120)->nullable()->unique();
            $t->foreignId("replacement_of_id")->nullable()->unique()->constrained("payments");
            $t->uuid("idempotency_key")->unique();
            $t->string("request_hash", 64);
            $t->string("status", 20)->default("draft")->index();
            $t->string("reconciliation_status", 20)->default("pending")->index();
            $t->foreignId("recorded_by")->constrained("users");
            $t->foreignId("posted_by")->nullable()->constrained("users");
            $t->timestamp("posted_at")->nullable();
            $t->foreignId("reconciled_by")->nullable()->constrained("users");
            $t->timestamp("reconciled_at")->nullable();
            $t->text("remarks")->nullable();
            $t->timestamps();
        });
        Schema::create("payment_allocations", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("payment_id")->constrained();
            $t->foreignId("rental_bill_id")->constrained();
            $t->decimal("amount", 14, 2);
            $t->unique(["payment_id", "rental_bill_id"]);
            $t->timestamps();
        });
        Schema::create("payment_reversals", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("payment_id")->unique()->constrained();
            $t->text("reason");
            $t->foreignId("reversed_by")->constrained("users");
            $t->timestamps();
        });
        Schema::create("billing_adjustments", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->uuid("idempotency_key")->unique();
            $t->string("request_hash", 64);
            $t->foreignId("rental_bill_id")->constrained();
            $t->decimal("amount", 14, 2);
            $t->text("reason");
            $t->string("approval_reference");
            $t->foreignId("created_by")->constrained("users");
            $t->timestamps();
        });
        Schema::create("audit_logs", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->foreignId("actor_id")->nullable()->constrained("users");
            $t->string("action", 80)->index();
            $t->string("entity_type", 100)->nullable();
            $t->unsignedBigInteger("entity_id")->nullable();
            $t->json("old_values")->nullable();
            $t->json("new_values")->nullable();
            $t->string("ip_address", 45)->nullable();
            $t->text("reason")->nullable();
            $t->timestamps();
        });
        Schema::create("system_settings", function (Blueprint $t) {
            $t->engine = "InnoDB";
            $t->id();
            $t->string("key")->unique();
            $t->text("value")->nullable();
            $t->timestamps();
        });
    }
    public function down()
    {
        foreach (
            [
                "system_settings",
                "audit_logs",
                "billing_adjustments",
                "payment_reversals",
                "payment_allocations",
                "payments",
                "rental_bill_items",
                "rental_bills",
                "rental_agreements",
                "stall_assignments",
                "rental_rates",
                "businesses",
                "tenants",
                "stall_layout_positions",
                "stalls",
                "stall_categories",
                "market_layouts",
                "market_sections",
                "markets",
            ]
            as $table
        ) {
            Schema::dropIfExists($table);
        }
        Schema::table("users", function (Blueprint $t) {
            $t->dropColumn("active");
        });
    }
}
