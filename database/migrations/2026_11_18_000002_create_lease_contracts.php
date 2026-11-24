<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateLeaseContracts extends Migration
{
    public function up()
    {
        Schema::create("contract_templates", function (Blueprint $t) {
            $t->id();
            $t->string("name");
            $t->string("version", 80)->unique();
            $t->string("path");
            $t->string("source_checksum", 64);
            $t->string("checksum", 64);
            $t->boolean("approved")->default(false);
            $t->date("effective_date")->nullable();
            $t->foreignId("created_by")->constrained("users");
            $t->foreignId("approved_by")->nullable()->constrained("users");
            $t->timestamp("approved_at")->nullable();
            $t->text("approval_reference")->nullable();
            $t->timestamps();
        });
        Schema::create("contract_sequences", function (Blueprint $t) {
            $t->string("period", 7)->primary();
            $t->unsignedInteger("last_number")->default(0);
        });
        Schema::create("lease_contracts", function (Blueprint $t) {
            $t->id();
            $t->foreignId("tenant_id")->constrained();
            $t->foreignId("stall_assignment_id")->constrained();
            $t->foreignId("contract_template_id")->constrained();
            $t->string("control_number", 100)->unique();
            $t->uuid("submission_key")->unique();
            $t->string("request_hash", 64);
            $t->string("status", 24)->default("draft")->index();
            $t->date("start_date");
            $t->date("end_date");
            $t->date("execution_date");
            $t->unsignedSmallInteger("billing_days");
            foreach (
                [
                    "floor_area",
                    "rental_rate_sqm",
                    "daily_cusa",
                    "daily_rental",
                    "monthly_rental",
                    "monthly_cusa",
                    "total_monthly_rental_cusa",
                    "advance_rental",
                    "security_deposit",
                    "total_initial_payment",
                ]
                as $field
            ) {
                $t->decimal($field, 14, 2);
            }
            $t->text("permitted_goods");
            $t->longText("snapshot")->nullable();
            $t->string("docx_path")->nullable();
            $t->string("docx_checksum", 64)->nullable();
            $t->string("pdf_path")->nullable();
            $t->string("pdf_checksum", 64)->nullable();
            $t->string("signed_path")->nullable();
            $t->string("signed_checksum", 64)->nullable();
            $t->foreignId("created_by")->constrained("users");
            foreach (["generated_by", "approved_by", "issued_by"] as $field) {
                $t->foreignId($field)->nullable()->constrained("users");
            }
            foreach (
                [
                    "generated_at",
                    "approved_at",
                    "issued_at",
                    "signed_at",
                    "activated_at",
                    "closed_at",
                ]
                as $field
            ) {
                $t->timestamp($field)->nullable();
            }
            $t->text("reason")->nullable();
            $t->text("visual_review_reference")->nullable();
            $t->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists("lease_contracts");
        Schema::dropIfExists("contract_sequences");
        Schema::dropIfExists("contract_templates");
    }
}
