<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

class CreateRentalOverrides extends Migration
{
    public function up()
    {
        Schema::table('rental_policies', function (Blueprint $t) {
            $t->boolean('allow_out_of_range_overrides')->default(false);
            $t->boolean('allow_zero_rent_overrides')->default(false);
            $t->string('override_authority_reference')->nullable();
        });
        Schema::create('rental_overrides', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('stall_assignment_id')->constrained();
            $t->foreignId('initial_assessment_id')->constrained('rental_assessments');
            $t->foreignId('source_override_id')->nullable()->constrained('rental_overrides');
            $t->unsignedInteger('revision');
            $t->uuid('submission_key')->unique();
            $t->string('request_hash', 64);
            $t->string('override_type', 20);
            $t->decimal('automatic_rate_per_sqm', 14, 2);
            $t->decimal('automatic_daily_rental', 14, 2);
            $t->decimal('proposed_daily_rental', 14, 2);
            $t->decimal('override_rate_per_sqm', 14, 2)->nullable();
            $t->decimal('override_daily_amount', 14, 2)->nullable();
            $t->decimal('rental_difference', 14, 2);
            $t->text('reason');
            $t->string('reference_document')->nullable();
            $t->text('remarks')->nullable();
            $t->date('effective_from');
            $t->date('effective_until')->nullable();
            $t->string('status', 12)->default('draft');
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('updated_by')->constrained('users');
            $t->timestamp('submitted_at')->nullable();
            foreach (['approved', 'rejected', 'revoked'] as $action) {
                $t->foreignId($action.'_by')->nullable()->constrained('users');
                $t->timestamp($action.'_at')->nullable();
            }
            $t->foreignId('rental_policy_id')->nullable()->constrained();
            $t->json('authority_snapshot')->nullable();
            $t->string('approval_fingerprint', 64)->nullable();
            $t->text('rejection_reason')->nullable();
            $t->text('revocation_reason')->nullable();
            $t->date('revocation_effective_from')->nullable();
            $t->timestamps();
            $t->unique(['stall_assignment_id', 'revision']);
            $t->index(['stall_assignment_id', 'status', 'effective_from'], 'override_assignment_status_start');
        });
        Schema::table('rental_assessments', function (Blueprint $t) {
            $t->string('computation_mode', 20)->default('automatic');
            $t->decimal('automatic_rate_per_sqm', 14, 2)->nullable();
            $t->decimal('automatic_daily_rental', 14, 2)->nullable();
            $t->decimal('rental_difference', 14, 2)->default(0);
            $t->string('market_section_name')->nullable();
            $t->decimal('equivalent_rate_per_sqm', 14, 2)->nullable();
            $t->boolean('contract_rate_consistent')->default(true);
        });
        foreach (['market_section_id' => 'market_sections', 'rental_override_id' => 'rental_overrides'] as $key => $target) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("ALTER TABLE rental_assessments ADD COLUMN {$key} INTEGER REFERENCES {$target}(id)");
            } else {
                Schema::table('rental_assessments', function (Blueprint $t) use ($key, $target) {
                    $t->foreignId($key)->nullable()->constrained($target);
                });
            }
        }
    }

    public function down()
    {
        throw new LogicException('Retain approved policies, requests and financial snapshots. Use a reviewed forward correction.');
    }
}
