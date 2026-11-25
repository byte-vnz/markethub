<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

class SupportFixedDayBilling extends Migration
{
    public function up(): void
    {
        $this->migrate(true);
    }

    public function down(): void
    {
        $this->migrate(false);
    }

    private function migrate(bool $enable): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->changeSchema($enable, false);
            return;
        }

        if (DB::transactionLevel() > 0) {
            throw new LogicException('The fixed-day migration needs its own SQLite transaction to rebuild referenced tables safely.');
        }

        // SQLite cannot change foreign key enforcement within a transaction.
        $foreignKeys = (bool) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
        if ($foreignKeys) {
            Schema::disableForeignKeyConstraints();
        }
        try {
            DB::transaction(function () use ($enable) {
                $violations = $this->sqliteForeignKeyViolations();
                $this->changeSchema($enable, true);
                if ($this->sqliteForeignKeyViolations() !== $violations) {
                    throw new LogicException('The fixed-day migration changed existing SQLite foreign key violations.');
                }
            });
        } finally {
            if ($foreignKeys) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    private function changeSchema(bool $enable, bool $sqlite): void
    {
        $coverage = ['coverage_start', 'coverage_end'];
        $existing = array_intersect($coverage, Schema::getColumnListing('rental_bills'));
        if (!$enable) {
            $this->assertNoFixedDayHistory($existing, $sqlite);
        }

        $columns = $enable ? array_diff($coverage, $existing) : $existing;
        $coverageChanges = array_map(function ($column) use ($enable) {
            return $enable ? "ADD COLUMN {$column} DATE NULL" : "DROP COLUMN {$column}";
        }, $columns);

        if ($sqlite) {
            // SQLite's existing VARCHAR affinity already accepts both period formats.
            $this->rebuildSqliteAssessments($enable);
            foreach ($coverageChanges as $change) {
                DB::statement("ALTER TABLE rental_bills {$change}");
            }
            return;
        }

        $width = $enable ? 10 : 7;
        $nullability = $enable ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE rental_assessments MODIFY period VARCHAR({$width}) NOT NULL, MODIFY rental_policy_id BIGINT UNSIGNED {$nullability}");
        $billChanges = implode(', ', array_merge(["MODIFY period VARCHAR({$width}) NOT NULL"], $coverageChanges));
        DB::statement("ALTER TABLE rental_bills {$billChanges}");
    }

    private function assertNoFixedDayHistory(array $coverage, bool $sqlite): void
    {
        $length = $sqlite ? 'length' : 'char_length';
        $assessments = DB::table('rental_assessments')->useWritePdo()
            ->whereRaw("{$length}(period) > 7")
            ->orWhereNull('rental_policy_id')
            ->orWhere('basis->billing_cycle', 'fixed_days');

        $bills = DB::table('rental_bills')->useWritePdo()->whereRaw("{$length}(period) > 7");
        foreach ($coverage as $column) {
            $bills->orWhereNotNull($column);
        }

        if ($assessments->exists() || $bills->exists()) {
            throw new LogicException('Retain fixed-day billing records. Use a forward migration to preserve financial history.');
        }
    }

    private function rebuildSqliteAssessments(bool $nullable): void
    {
        $table = 'rental_assessments';
        $shadow = 'new_rental_assessments';
        $schema = (string) DB::table('sqlite_master')->where('name', $table)->where('type', 'table')->value('sql');
        $children = DB::table('sqlite_master')->where('tbl_name', $table)
            ->whereIn('type', ['index', 'trigger'])->whereNotNull('sql')->pluck('sql');
        $sequence = DB::table('sqlite_sequence')->where('name', $table)->value('seq');

        // Keep the existing table definition so prior columns, defaults and constraints are copied verbatim.
        $create = preg_replace('/^CREATE TABLE "rental_assessments"(?=\s*\()/i', 'CREATE TABLE "'.$shadow.'"', $schema, 1, $renamed);
        $policy = '"rental_policy_id" integer'.($nullable ? '' : ' not null');
        $create = preg_replace('/"rental_policy_id" integer(?: not null)?(?=,)/i', $policy, $create, -1, $changed);
        if ($renamed !== 1 || $changed !== 1) {
            throw new LogicException('Unexpected rental assessments table definition. Review the SQLite schema before migrating.');
        }

        $columns = DB::connection()->getQueryGrammar()->columnize(Schema::getColumnListing($table));
        DB::statement($create);
        DB::statement("INSERT INTO {$shadow} ({$columns}) SELECT {$columns} FROM {$table}");
        Schema::drop($table);
        Schema::rename($shadow, $table);
        foreach ($children as $definition) {
            DB::statement($definition);
        }
        if ($sequence !== null) {
            DB::table('sqlite_sequence')->updateOrInsert(['name' => $table], ['seq' => $sequence]);
        }
    }

    private function sqliteForeignKeyViolations(): array
    {
        $violations = array_map('json_encode', DB::select('PRAGMA foreign_key_check'));
        sort($violations, SORT_STRING);

        return $violations;
    }
}
