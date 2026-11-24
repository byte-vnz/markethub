<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

class CreateRentalAssessments extends Migration
{
    public function up()
    {
        Schema::create("rental_assessments", function (Blueprint $table) {
            $table->engine = "InnoDB";
            $table->id();
            $table->foreignId("stall_assignment_id")->constrained();
            $table->foreignId("business_id")->constrained();
            $table->foreignId("business_category_id")->constrained();
            $table->foreignId("business_category_rental_rate_id")->constrained();
            $table->foreignId("rental_policy_id")->constrained();
            $table->foreignId("market_id")->constrained();
            $table->foreignId("created_by")->constrained("users");
            $table->string("period", 7);
            $table->string("purpose", 10);
            $table->date("effective_from");
            $table->date("effective_until")->nullable();
            $table->string("market_name");
            $table->string("stall_code");
            $table->string("business_category_name");
            $table->string("location_type", 5);
            $table->unsignedSmallInteger("billing_days");
            foreach ([
                "floor_area", "minimum_rate", "maximum_rate", "rental_rate_sqm", "daily_cusa",
                "daily_rental", "monthly_rental", "monthly_cusa", "total_monthly_rental_cusa",
                "advance_rental", "security_deposit", "total_initial_payment",
            ] as $field) {
                $table->decimal($field, 14, 2);
            }
            $table->json("basis");
            $table->longText("snapshot");
            $table->unique(["stall_assignment_id", "period", "purpose"], "rental_assessment_assignment_period_purpose");
            $table->timestamps();
        });
        Schema::table("stall_assignments", function (Blueprint $table) {
            $table->string("concurrent_authorization_reference", 120)->nullable();
            $table->text("concurrent_authorization_reason")->nullable();
        });

        $this->allowAssessmentsOnAgreements(true);
        foreach (["rental_agreements", "rental_bills", "lease_contracts"] as $name) {
            if (DB::getDriverName() === "sqlite") {
                DB::statement("ALTER TABLE {$name} ADD COLUMN rental_assessment_id INTEGER REFERENCES rental_assessments(id)");
            } else {
                Schema::table($name, function (Blueprint $table) {
                    $table->foreignId("rental_assessment_id")->nullable()->constrained();
                });
            }
        }
    }

    public function down()
    {
        if (DB::table("rental_assessments")->exists() || DB::table("rental_agreements")->whereNull("rental_rate_id")->exists()) {
            throw new LogicException("Rental assessments contain financial history. Use a forward migration to preserve it.");
        }
        foreach (["rental_agreements", "rental_bills", "lease_contracts"] as $name) {
            if (DB::getDriverName() === "sqlite") {
                DB::statement("ALTER TABLE {$name} DROP COLUMN rental_assessment_id");
            } else {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropForeign(["rental_assessment_id"]);
                    $table->dropColumn("rental_assessment_id");
                });
            }
        }
        $this->allowAssessmentsOnAgreements(false);
        if (DB::getDriverName() === "sqlite") {
            DB::statement("ALTER TABLE stall_assignments DROP COLUMN concurrent_authorization_reference");
            DB::statement("ALTER TABLE stall_assignments DROP COLUMN concurrent_authorization_reason");
        } else {
            Schema::table("stall_assignments", function (Blueprint $table) {
                $table->dropColumn(["concurrent_authorization_reference", "concurrent_authorization_reason"]);
            });
        }
        Schema::drop("rental_assessments");
    }

    private function allowAssessmentsOnAgreements(bool $nullable): void
    {
        if (DB::getDriverName() !== "sqlite") {
            $columnDefinition = $nullable ? "NULL" : "NOT NULL";
            DB::statement("ALTER TABLE rental_agreements MODIFY rental_rate_id BIGINT UNSIGNED {$columnDefinition}");
            return;
        }

        // SQLite cannot alter nullability in place. Preserve the IDs, foreign keys and existing agreement values.
        DB::transaction(function () use ($nullable) {
            $sequence = DB::table("sqlite_sequence")->where("name", "rental_agreements")->value("seq");
            $shadow = $nullable ? "rental_agreements_new" : "rental_agreements_legacy";
            Schema::create($shadow, function (Blueprint $table) use ($nullable) {
                $table->id();
                $table->foreignId("stall_assignment_id")->unique()->constrained();
                $table->foreignId("rental_rate_id")->nullable($nullable)->constrained();
                $table->string("reference")->unique();
                $table->decimal("monthly_amount", 14, 2);
                $table->date("start_date");
                $table->date("end_date")->nullable();
                $table->timestamps();
            });
            $columns = "id, stall_assignment_id, rental_rate_id, reference, monthly_amount, start_date, end_date, created_at, updated_at";
            DB::statement("INSERT INTO {$shadow} ({$columns}) SELECT {$columns} FROM rental_agreements");
            Schema::drop("rental_agreements");
            Schema::rename($shadow, "rental_agreements");
            if ($sequence !== null) {
                DB::table("sqlite_sequence")->updateOrInsert(["name" => "rental_agreements"], ["seq" => $sequence]);
            }
        });
    }
}
