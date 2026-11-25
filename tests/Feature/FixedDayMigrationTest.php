<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{Crypt, DB, Schema};
use LogicException;
use Tests\TestCase;

class FixedDayMigrationTest extends TestCase
{
    private const MIGRATION = '2026_11_25_000001_support_fixed_day_billing';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);

        $migrations = app('migrator')->getMigrationFiles([database_path('migrations')]);
        $prior = array_filter($migrations, function ($name) {
            return $name < self::MIGRATION;
        }, ARRAY_FILTER_USE_KEY);
        $this->artisan('migrate', ['--realpath' => true, '--path' => array_values($prior)])->assertExitCode(0);
        $this->seedHistory();
    }

    public function test_upgrade_and_safe_rollback_preserve_existing_financial_records_and_links(): void
    {
        DB::statement('CREATE TABLE historical_assessment_links (id INTEGER PRIMARY KEY, assessment_id INTEGER REFERENCES rental_assessments(id) ON DELETE CASCADE)');
        DB::table('historical_assessment_links')->insert(['id' => 1, 'assessment_id' => 41]);
        $before = $this->history();

        $this->upgrade();
        $upgraded = $before;
        $upgraded['bill'] += ['coverage_start' => null, 'coverage_end' => null];
        $this->assertSame($upgraded, $this->history());
        $this->assertDatabaseHas('historical_assessment_links', ['id' => 1, 'assessment_id' => 41]);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->assertSame(95, (int) DB::table('sqlite_sequence')->where('name', 'rental_assessments')->value('seq'));

        $this->rollback();

        $this->assertSame($before, $this->history());
        $this->assertDatabaseHas('historical_assessment_links', ['id' => 1, 'assessment_id' => 41]);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->assertDatabaseRejects(function () {
            DB::table('rental_assessments')->where('id', 41)->update(['rental_policy_id' => null]);
        }, 'NOT NULL constraint failed');
    }

    public function test_fixed_day_rows_keep_unique_periods_and_restrict_invalid_policy_links(): void
    {
        $this->upgrade();

        $assessment = $this->copyRow('rental_assessments', 41);
        $assessment['rental_policy_id'] = null;
        $assessment['period'] = '2030-01-07';
        $assessment['purpose'] = 'billing';
        $fixedId = DB::table('rental_assessments')->insertGetId($assessment);
        $this->assertSame(96, $fixedId);

        $bill = array_replace($this->copyRow('rental_bills', 61), [
            'reference' => 'FIC-BILL-WEEK', 'rental_assessment_id' => $fixedId,
            'period' => '2030-01-07', 'coverage_start' => '2030-01-07', 'coverage_end' => '2030-01-13',
        ]);
        DB::table('rental_bills')->insert($bill);
        $this->assertDatabaseHas('rental_bills', $bill);

        $this->assertDatabaseRejects(function () use ($assessment) {
            DB::table('rental_assessments')->insert($assessment);
        }, 'UNIQUE constraint failed');
        $this->assertDatabaseRejects(function () use ($bill) {
            DB::table('rental_bills')->insert(array_replace($bill, ['reference' => 'FIC-DUPLICATE']));
        }, 'UNIQUE constraint failed');
        $this->assertDatabaseRejects(function () use ($fixedId) {
            DB::table('rental_assessments')->where('id', $fixedId)->update(['rental_policy_id' => 999]);
        }, 'FOREIGN KEY constraint failed');
        $this->assertDatabaseRejects(function () {
            DB::table('rental_assessments')->where('id', 41)->delete();
        }, 'FOREIGN KEY constraint failed');
    }

    /** @dataProvider fixedDayRollbackMarkers */
    public function test_rollback_refuses_fixed_day_data(string $table, int $id, array $changes): void
    {
        $this->upgrade();
        DB::table($table)->where('id', $id)->update($changes);
        $before = $this->history();

        try {
            $this->rollback();
        } catch (LogicException $exception) {
            $this->assertStringContainsString('fixed-day', $exception->getMessage());
            $this->assertSame($before, $this->history());
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
            return;
        }

        $this->fail('Rollback must preserve existing fixed-day records.');
    }

    public static function fixedDayRollbackMarkers(): array
    {
        return [
            'fixed-day basis with a month key and policy' => ['rental_assessments', 41, ['basis' => '{"billing_cycle":"fixed_days"}']],
            'nullable policy' => ['rental_assessments', 41, ['rental_policy_id' => null]],
            'assessment date key' => ['rental_assessments', 41, ['period' => '2030-01-07']],
            'bill date key' => ['rental_bills', 61, ['period' => '2030-01-07']],
            'bill coverage start' => ['rental_bills', 61, ['coverage_start' => '2030-01-07']],
            'bill coverage end' => ['rental_bills', 61, ['coverage_end' => '2030-01-13']],
        ];
    }

    private function upgrade(): void
    {
        $this->artisan('migrate')->assertExitCode(0);
        $this->assertTrue(Schema::hasColumns('rental_bills', ['coverage_start', 'coverage_end']), 'Bills need both coverage dates.');
    }

    private function rollback(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertExitCode(0);
    }

    private function history(): array
    {
        return [
            'assessment' => (array) DB::table('rental_assessments')->where('id', 41)->first(),
            'agreement' => (array) DB::table('rental_agreements')->where('id', 51)->first(),
            'bill' => (array) DB::table('rental_bills')->where('id', 61)->first(),
            'item' => (array) DB::table('rental_bill_items')->where('id', 71)->first(),
            'override' => (array) DB::table('rental_overrides')->where('id', 81)->first(),
        ];
    }

    private function copyRow(string $table, int $id): array
    {
        $row = (array) DB::table($table)->where('id', $id)->first();
        unset($row['id']);

        return $row;
    }

    private function assertDatabaseRejects(callable $change, string $constraint): void
    {
        try {
            $change();
        } catch (QueryException $exception) {
            $this->assertStringContainsString($constraint, $exception->getMessage());
            return;
        }

        $this->fail('Expected database constraint failure.');
    }

    private function seedHistory(): void
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'Fictional operator', 'email' => 'migration@example.test', 'password' => 'fixture']);
        DB::table('markets')->insert(['id' => 1, 'code' => 'FIC', 'name' => 'Fictional market']);
        DB::table('market_sections')->insert(['id' => 1, 'market_id' => 1, 'code' => 'FIC', 'name' => 'Fictional section']);
        DB::table('stalls')->insert(['id' => 1, 'market_id' => 1, 'market_section_id' => 1, 'code' => 'S1', 'classification' => 'Legacy', 'area' => '10.00']);
        DB::table('tenants')->insert(['id' => 1, 'reference' => 'FIC-001', 'name' => 'Fictional tenant']);
        DB::table('business_categories')->insert(['id' => 1, 'code' => 'PRODUCE', 'name' => 'Produce']);
        DB::table('business_category_rental_rates')->insert(['id' => 1, 'business_category_id' => 1, 'minimum_rate' => '10.00', 'maximum_rate' => '12.50']);
        DB::table('businesses')->insert(['id' => 1, 'tenant_id' => 1, 'business_category_id' => 1, 'name' => 'Fictional stand']);
        DB::table('stall_assignments')->insert(['id' => 1, 'stall_id' => 1, 'tenant_id' => 1, 'business_id' => 1, 'start_date' => '2030-01-01', 'authorized_by' => 1]);
        DB::table('rental_policies')->insert([
            'id' => 1, 'market_id' => 1, 'daily_cusa' => '2.75', 'billing_day_rule' => 'calendar',
            'max_concurrent_assignments' => 1, 'advance_deposit_authorized' => false,
        ]);
        DB::table('rental_assessments')->insert([
            'id' => 41, 'stall_assignment_id' => 1, 'business_id' => 1, 'business_category_id' => 1,
            'business_category_rental_rate_id' => 1, 'rental_policy_id' => 1, 'market_id' => 1, 'created_by' => 1,
            'period' => '2030-01', 'purpose' => 'initial', 'effective_from' => '2030-01-01', 'billing_days' => 31,
            'market_name' => 'Fictional market', 'market_section_id' => 1, 'market_section_name' => 'Fictional section',
            'stall_code' => 'S1', 'business_category_name' => 'Produce', 'location_type' => 'Inner',
            'floor_area' => '10.00', 'minimum_rate' => '10.00', 'maximum_rate' => '12.50', 'rental_rate_sqm' => '10.00',
            'daily_rental' => '100.00', 'daily_cusa' => '2.75', 'monthly_rental' => '3100.00', 'monthly_cusa' => '85.25',
            'total_monthly_rental_cusa' => '3185.25', 'advance_rental' => '0.00', 'security_deposit' => '0.00',
            'total_initial_payment' => '0.00', 'basis' => '{"billing_day_rule":"calendar"}',
            'snapshot' => Crypt::encryptString('{"tenant":"Fictional tenant"}'),
        ]);
        DB::table('sqlite_sequence')->where('name', 'rental_assessments')->update(['seq' => 95]);
        DB::table('rental_agreements')->insert([
            'id' => 51, 'stall_assignment_id' => 1, 'rental_assessment_id' => 41,
            'reference' => 'FIC-AGREEMENT', 'monthly_amount' => '3185.25', 'start_date' => '2030-01-01',
        ]);
        DB::table('rental_bills')->insert([
            'id' => 61, 'reference' => 'FIC-BILL', 'stall_assignment_id' => 1, 'tenant_id' => 1, 'stall_id' => 1,
            'period' => '2030-01', 'due_date' => '2030-01-31', 'amount' => '3185.25', 'status' => 'posted',
            'created_by' => 1, 'posted_by' => 1, 'posted_at' => '2030-01-31 10:00:00', 'rental_assessment_id' => 41,
        ]);
        DB::table('rental_bill_items')->insert(['id' => 71, 'rental_bill_id' => 61, 'description' => 'Historical rent and CUSA', 'amount' => '3185.25']);
        DB::table('rental_overrides')->insert([
            'id' => 81, 'stall_assignment_id' => 1, 'initial_assessment_id' => 41, 'revision' => 1,
            'submission_key' => '10000000-0000-4000-8000-000000000000', 'request_hash' => str_repeat('a', 64),
            'override_type' => 'daily_amount', 'automatic_rate_per_sqm' => '10.00', 'automatic_daily_rental' => '100.00',
            'proposed_daily_rental' => '110.00', 'rental_difference' => '10.00', 'reason' => 'Fictional request',
            'effective_from' => '2030-02-01', 'requested_by' => 1, 'updated_by' => 1,
        ]);
    }
}
