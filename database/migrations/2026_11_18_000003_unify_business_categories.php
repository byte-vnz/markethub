<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnifyBusinessCategories extends Migration
{
    public function up()
    {
        $this->applyMigration(function () {
            $this->addSchema();
        });
    }

    private function addSchema(): void
    {
        Schema::create('business_categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('business_category_rental_rates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('business_category_id')->constrained();
            $table->decimal('minimum_rate', 14, 2)->nullable();
            $table->decimal('maximum_rate', 14, 2)->nullable();
            $this->scheduleColumns($table);
            $table->index(['business_category_id', 'status', 'effective_from'], 'category_rates_effectivity');
        });

        Schema::create('rental_policies', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('market_id')->constrained();
            $table->decimal('daily_cusa', 14, 2);
            $table->string('billing_day_rule', 10);
            $table->unsignedSmallInteger('fixed_billing_days')->nullable();
            $table->unsignedSmallInteger('max_concurrent_assignments');
            $table->boolean('advance_deposit_authorized');
            $this->scheduleColumns($table);
            $table->index(['market_id', 'status', 'effective_from'], 'rental_policies_effectivity');
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteBusinesses(true);
        } else {
            DB::statement('ALTER TABLE businesses MODIFY name VARCHAR(255) NULL');
            Schema::table('businesses', function (Blueprint $table) {
                $table->foreignId('business_category_id')->nullable()->constrained();
                $table->string('line_of_business')->nullable();
                $table->text('permitted_goods')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->foreignId('updated_by')->nullable()->constrained('users');
            });
        }

        Schema::table('stalls', function (Blueprint $table) {
            $table->string('location_type', 5)->nullable()->index();
        });
        Schema::table('tenants', function (Blueprint $table) {
            // Encryption/base64 expands valid multi-business UTF-8 draft payloads beyond MySQL TEXT.
            $table->mediumText('registration_draft')->nullable();
        });
    }

    public function down()
    {
        $this->applyMigration(function () {
            $this->removeSchema();
        });
    }

    private function removeSchema(): void
    {
        if (DB::table('business_categories')->exists() || DB::table('rental_policies')->exists()
            || DB::table('stalls')->whereNotNull('location_type')->exists()
            || DB::table('tenants')->whereNotNull('registration_draft')->exists()
            || DB::table('businesses')->whereNull('name')->orWhereNotNull('line_of_business')->orWhereNotNull('permitted_goods')
                ->orWhereNotNull('remarks')->orWhereNotNull('created_by')->orWhereNotNull('updated_by')->exists()) {
            throw new LogicException('Category, rental policy or registration data exists. Use a forward migration to preserve these records.');
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteBusinesses(false);
            DB::statement('DROP INDEX stalls_location_type_index');
            DB::statement('ALTER TABLE stalls DROP COLUMN location_type');
            DB::statement('ALTER TABLE tenants DROP COLUMN registration_draft');
        } else {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropForeign(['business_category_id']);
                $table->dropForeign(['created_by']);
                $table->dropForeign(['updated_by']);
                $table->dropColumn(['business_category_id', 'line_of_business', 'permitted_goods', 'remarks', 'created_by', 'updated_by']);
            });
            DB::statement('ALTER TABLE businesses MODIFY name VARCHAR(255) NOT NULL');
            Schema::table('stalls', function (Blueprint $table) {
                $table->dropColumn('location_type');
            });
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('registration_draft');
            });
        }

        Schema::dropIfExists('rental_policies');
        Schema::dropIfExists('business_category_rental_rates');
        Schema::dropIfExists('business_categories');
    }

    private function scheduleColumns(Blueprint $table): void
    {
        $table->date('effective_from')->nullable();
        $table->date('effective_until')->nullable();
        $table->string('status', 10)->default('draft');
        $table->string('reference_document')->nullable();
        $table->text('remarks')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users');
        $table->foreignId('approved_by')->nullable()->constrained('users');
        $table->timestamp('approved_at')->nullable();
        $table->timestamps();
    }

    private function applyMigration(callable $change): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $change();
            return;
        }

        $foreignKeys = (bool) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
        if ($foreignKeys && DB::transactionLevel() > 0) {
            throw new LogicException('The business schema migration needs its own SQLite transaction to rebuild referenced tables safely.');
        }
        if ($foreignKeys) {
            Schema::disableForeignKeyConstraints();
        }
        try {
            DB::transaction(function () use ($change) {
                $before = $this->sqliteViolations();
                $change();
                if ($this->sqliteViolations() !== $before) {
                    throw new LogicException('Business schema migration changed the legacy foreign key violations.');
                }
            });
        } finally {
            if ($foreignKeys) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    private function sqliteViolations(): array
    {
        // Constraint positions can change during the rebuild even when the child and parent rows do not.
        $violations = array_map(function ($violation) {
            return serialize([$violation->table, $violation->rowid, $violation->parent]);
        }, DB::select('PRAGMA foreign_key_check'));
        sort($violations, SORT_STRING);
        return $violations;
    }

    private function rebuildSqliteBusinesses(bool $allowBlankName): void
    {
        // Rebuild through a shadow table so incoming assignment foreign keys keep the original table name.
        $sequence = DB::table('sqlite_sequence')->where('name', 'businesses')->value('seq');
        Schema::create('new_businesses', function (Blueprint $table) use ($allowBlankName) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name')->nullable($allowBlankName);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('permit_reference')->nullable();
            $table->string('registration_reference')->nullable();
            $table->date('permit_from')->nullable();
            $table->date('permit_until')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            if ($allowBlankName) {
                $table->foreignId('business_category_id')->nullable()->constrained();
                $table->string('line_of_business')->nullable();
                $table->text('permitted_goods')->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->foreignId('updated_by')->nullable()->constrained('users');
            }
        });

        $legacyColumns = 'id, tenant_id, name, category, description, permit_reference, registration_reference, permit_from, permit_until, status, created_at, updated_at';
        DB::statement('INSERT INTO new_businesses ('.$legacyColumns.') SELECT '.$legacyColumns.' FROM businesses');
        Schema::drop('businesses');
        Schema::rename('new_businesses', 'businesses');
        if ($sequence !== null) {
            DB::table('sqlite_sequence')->updateOrInsert(['name' => 'businesses'], ['seq' => $sequence]);
        }
    }
}
