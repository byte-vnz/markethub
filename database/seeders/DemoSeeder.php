<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use App\Models\{
    User,
    Market,
    MarketSection,
    MarketLayout,
    StallCategory,
    Stall,
    StallLayoutPosition,
    Tenant,
    Business,
    BusinessCategory,
    BusinessCategoryRentalRate,
    RentalPolicy,
    SystemSetting,
};
use App\Services\{AssignmentService, BillingService, PaymentService};
class DemoSeeder extends Seeder
{
    public function run()
    {
        if (!app()->environment(["local", "testing"])) {
            throw new \RuntimeException("Demonstration seeding is local/testing only.");
        }
        $password = config("markethub.demo_password");
        if (!is_string($password) || strlen($password) < 16) {
            throw new \RuntimeException(
                "Set DEMO_PASSWORD to a unique local-only secret of at least 16 characters.",
            );
        }
        $this->call(RoleSeeder::class);
        $this->call(BusinessCategorySeeder::class);
        if (Market::where("code", "DEMO")->exists()) {
            if ($this->command) {
                $this->command->info(
                    "Demo market already exists; no records or passwords changed.",
                );
            }
            return;
        }
        foreach (
            ["superadmin", "marketadmin", "staff", "collector", "treasury", "viewer"]
            as $login
        ) {
            if (User::where("email", $login . "@markethub.test")->exists()) {
                throw new \RuntimeException(
                    "A demo account email already exists. No existing account will be promoted or reset.",
                );
            }
        }
        DB::transaction(function () use ($password) {
            $logins = [
                "superadmin" => "Super Administrator",
                "marketadmin" => "Market Administrator",
                "staff" => "Market Staff",
                "collector" => "Authorized Collection Officer",
                "treasury" => "Treasury/Accounting Officer",
                "viewer" => "LGU Management Viewer",
            ];
            $users = [];
            foreach ($logins as $login => $role) {
                $u = User::create([
                    "name" => "Demo " . $role,
                    "email" => $login . "@markethub.test",
                    "password" => Hash::make($password),
                    "active" => true,
                ]);
                $u->assignRole($role);
                $users[$login] = $u;
            }
            $admin = $users["superadmin"];
            $market = Market::create([
                "code" => "DEMO",
                "name" => "San Isidro Demonstration Public Market",
                "address" => "Fictional LGU training location",
                "status" => "active",
            ]);
            $layout = MarketLayout::create([
                "market_id" => $market->id,
                "name" => "Main market floor",
                "columns" => 6,
                "rows" => 8,
            ]);
            $category = StallCategory::firstOrCreate(["name" => "Standard stall"]);
            $start = today()->subMonthsNoOverflow(2)->startOfMonth()->toDateString();
            if (BusinessCategory::where('code','DEMO-TRAINING')->exists()) {
                throw new \RuntimeException('The synthetic training category already exists without its demo market; reconcile it before seeding.');
            }
            $businessCategory = BusinessCategory::create([
                'code'=>'DEMO-TRAINING','name'=>'Fictional training trade','is_active'=>true,
                'description'=>'Synthetic demonstration category; not an official LGU rate schedule.',
                'created_by'=>$admin->id,'updated_by'=>$admin->id,
            ]);
            BusinessCategoryRentalRate::create([
                'business_category_id'=>$businessCategory->id,'minimum_rate'=>'20.00','maximum_rate'=>'25.00',
                'effective_from'=>'2020-01-01','status'=>'approved',
                'reference_document'=>'SYNTHETIC-TRAINING-NOT-LGU-AUTHORITY',
                'created_by'=>$admin->id,'approved_by'=>$admin->id,'approved_at'=>now(),
            ]);
            RentalPolicy::create([
                'market_id'=>$market->id,'daily_cusa'=>'0.00','billing_day_rule'=>'calendar',
                'max_concurrent_assignments'=>1,'advance_deposit_authorized'=>true,
                'effective_from'=>'2020-01-01','status'=>'approved',
                'reference_document'=>'SYNTHETIC-TRAINING-NOT-LGU-AUTHORITY',
                'created_by'=>$admin->id,'approved_by'=>$admin->id,'approved_at'=>now(),
            ]);
            foreach (
                [
                    "A" => "Fresh produce",
                    "B" => "Meat and seafood",
                    "C" => "Dry goods",
                    "D" => "Food and essentials",
                ]
                as $letter => $name
            ) {
                $section = MarketSection::create([
                    "market_id" => $market->id,
                    "code" => $letter,
                    "name" => $name,
                    "status" => "active",
                ]);
                for ($i = 1; $i <= 12; $i++) {
                    $index = (ord($letter) - 65) * 12 + $i - 1;
                    $status = $i === 11 ? "reserved" : ($i === 12 ? "maintenance" : "vacant");
                    $stall = Stall::create([
                        "market_id" => $market->id,
                        "market_section_id" => $section->id,
                        "stall_category_id" => $category->id,
                        "code" => $letter . "-" . str_pad($i, 3, "0", STR_PAD_LEFT),
                        "floor" => "Ground floor",
                        "classification" => "standard",
                        "area" => "9.00",
                        "location_type" => $i % 2 === 0 ? "outer" : "inner",
                        "status" => $status,
                        "created_by" => $admin->id,
                        "updated_by" => $admin->id,
                    ]);
                    StallLayoutPosition::create([
                        "market_layout_id" => $layout->id,
                        "stall_id" => $stall->id,
                        "row" => intdiv($index, 6) + 1,
                        "column" => ($index % 6) + 1,
                        "width" => 1,
                        "height" => 1,
                    ]);
                    if ($i > 7) {
                        continue;
                    }
                    $tenant = Tenant::create([
                        "reference" => "DEMO-T-" . ($index + 1),
                        "name" => "Fictional Tenant " . ($index + 1),
                        "phone" => "00000000000",
                        "address" => "Demonstration address only",
                        "status" => "active",
                    ]);
                    $business = Business::create([
                        "tenant_id" => $tenant->id,
                        "business_category_id" => $businessCategory->id,
                        "permitted_goods" => "Fictional training merchandise",
                        "name" => "Sample " . $letter . " Store " . $i,
                        "category" => $name,
                        "status" => "active",
                    ]);
                    $a = app(AssignmentService::class)->assign($admin, [
                        "stall_id" => $stall->id,
                        "tenant_id" => $tenant->id,
                        "business_id" => $business->id,
                                                "start_date" => $start,
                        "agreement_reference" => "DEMO-AGR-" . ($index + 1),
                    ]);
                    for ($month = 2; $month >= 0; $month--) {
                        $date = today()->subMonthsNoOverflow($month)->startOfMonth();
                        $bill = app(BillingService::class)->generate(
                            $admin,
                            $a,
                            $date->format("Y-m"),
                            $date->copy()->day(10)->toDateString(),
                        );
                        app(BillingService::class)->post($admin, $bill);
                        if ($i % 3 === 0) {
                            continue;
                        }
                        $amount = $i % 3 === 1 ? (string)$bill->amount : "1000.00";
                        $p = app(PaymentService::class)->createDraft($admin, [
                            "tenant_id" => $tenant->id,
                            "payment_date" => $date->toDateString(),
                            "amount" => $amount,
                            "method" => "cash",
                            "receipt_reference" =>
                                "DEMO-REF-" . ($index + 1) . "-" . $date->format("Ym"),
                            "idempotency_key" => (string) Str::uuid(),
                            "allocations" => [$bill->id => $amount],
                            "remarks" => "Fictional training transaction; not an official receipt",
                        ]);
                        app(PaymentService::class)->post($admin, $p);
                        if ($i % 2 === 1) {
                            app(PaymentService::class)->reconcile(
                                $users["treasury"],
                                $p,
                                "Demonstration reconciliation only",
                            );
                        }
                    }
                }
            }
            SystemSetting::firstOrCreate(
                ["key" => "organization_name"],
                ["value" => "MarketHub LGU Training"],
            );
            SystemSetting::firstOrCreate(
                ["key" => "notice"],
                [
                    "value" =>
                        "Demonstration data: all tenants, businesses and financial records are fictional.",
                ],
            );
        });
        if ($this->command) {
            $this->command->info(
                "Created 48 stalls, four sections, six demo accounts and fictional ledgers. Password comes from DEMO_PASSWORD; remove it from .env after seeding.",
            );
        }
    }
}
