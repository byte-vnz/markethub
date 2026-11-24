<?php
namespace Tests\MySql;
use Tests\TestCase;
use App\Models\{User, Market, MarketSection, Stall, Tenant, RentalRate, RentalAgreement, StallAssignment, Payment};
use App\Services\{BillingService, PaymentService};
use Illuminate\Support\Str;
class ConcurrencyTest extends TestCase
{
    public function test_two_workers_cannot_overallocate_the_same_bill()
    {
        $this->assertSame("testing", app()->environment());
        $this->assertSame("mysql", config("database.default"));
        $this->assertSame("markethub_lgu_test", config("database.connections.mysql.database"));
        // This command destroys only the explicitly named disposable test database's tables.
        $this->artisan("migrate:fresh", ["--force" => true])->assertExitCode(0);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $u = User::create([
            "name" => "Concurrency fixture",
            "email" => "test@example.test",
            "password" => bcrypt(Str::random(40)),
            "active" => true,
        ]);
        $u->assignRole("Super Administrator");
        $m = Market::create(["code" => "TEST", "name" => "Test", "status" => "active"]);
        $s = MarketSection::create([
            "market_id" => $m->id,
            "code" => "A",
            "name" => "A",
            "status" => "active",
        ]);
        $stall = Stall::create([
            "market_id" => $m->id,
            "market_section_id" => $s->id,
            "code" => "A-001",
            "classification" => "standard",
            "area" => "9.00",
            "status" => "occupied",
        ]);
        $t = Tenant::create(["reference" => "T", "name" => "Test tenant", "status" => "active"]);
        $r = RentalRate::create([
            "market_id" => $m->id,
            "classification" => "standard",
            "amount" => "2500.00",
            "effective_from" => "2020-01-01",
            "approval_reference" => "TEST",
            "status" => "active",
        ]);
        $a = StallAssignment::create([
            "stall_id" => $stall->id,
            "active_stall_id" => $stall->id,
            "tenant_id" => $t->id,
            "authorized_by" => $u->id,
            "status" => "active",
            "start_date" => today()->startOfMonth()->toDateString(),
        ]);
        RentalAgreement::create([
            "stall_assignment_id" => $a->id,
            "rental_rate_id" => $r->id,
            "start_date" => today()->startOfMonth()->toDateString(),
            "reference" => "TEST",
            "monthly_amount" => $r->amount,
        ]);
        $b = app(BillingService::class)->generate(
            $u,
            $a,
            today()->format("Y-m"),
            today()->endOfMonth()->toDateString(),
        );
        app(BillingService::class)->post($u, $b);
        $payments = [];
        for ($i = 0; $i < 2; $i++) {
            $payments[] = app(PaymentService::class)->createDraft($u, [
                "tenant_id" => $t->id,
                "payment_date" => today()->toDateString(),
                "amount" => "2500.00",
                "method" => "cash",
                "receipt_reference" => "TEST-" . $i,
                "idempotency_key" => (string) Str::uuid(),
                "allocations" => [$b->id => "2500.00"],
            ]);
        }
        $environment = getenv();
        foreach (
            [
                "APP_ENV" => "testing",
                "DB_CONNECTION" => "mysql",
                "DB_DATABASE" => "markethub_lgu_test",
                "CACHE_DRIVER" => "array",
                "SESSION_DRIVER" => "array",
            ]
            as $key => $value
        ) {
            $environment[$key] = $value;
        }
        foreach (
            [
                "host" => "DB_HOST",
                "port" => "DB_PORT",
                "username" => "DB_USERNAME",
                "password" => "DB_PASSWORD",
            ]
            as $key => $env
        ) {
            $environment[$env] = (string) config("database.connections.mysql." . $key);
        }
        $start = microtime(true) + 2;
        $workers = [];
        foreach ($payments as $p) {
            $pipes = [];
            $process = proc_open(
                [
                    PHP_BINARY,
                    base_path("tests/MySql/post-worker.php"),
                    (string) $u->id,
                    (string) $p->id,
                    (string) $start,
                ],
                [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]],
                $pipes,
                base_path(),
                $environment,
            );
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $codes = [];
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);
            $this->assertContains($code, [0, 2], $output . $error);
            $codes[] = $code;
        }
        sort($codes);
        $this->assertSame([0, 2], $codes);
        $this->assertSame("0.00", $b->fresh()->balance());
        $this->assertSame(1, Payment::where("status", "posted")->count());
    }
}
