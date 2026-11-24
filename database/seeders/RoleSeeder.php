<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Role, Permission};
use Spatie\Permission\PermissionRegistrar;
class RoleSeeder extends Seeder
{
    public const MATRIX = [
        "Super Administrator" => [
            "rentals.override.view", "rentals.override.request", "rentals.override.history", "rentals.override.report",
            "rentals.override.approve", "rentals.override.reject", "rentals.override.revoke",
            "dashboard.view",
            "map.view",
            "markets.view",
            "markets.manage",
            "sections.view",
            "sections.manage",
            "stalls.view",
            "stalls.manage",
            "tenants.view",
            "tenants.manage",
            "assignments.view",
            "assignments.manage",
            "rates.view",
            "rates.manage",
            "rates.approve",
            "billing.view",
            "billing.generate",
            "billing.post",
            "billing.adjust",
            "payments.view",
            "payments.record",
            "payments.post",
            "payments.reverse",
            "payments.reconcile",
            "reports.view",
            "reports.financial",
            "reports.personal",
            "audit.view",
            "staff.manage",
            "settings.manage",
            "financial.backdate",
        ],
        "Market Administrator" => [
            "rentals.override.view", "rentals.override.request", "rentals.override.history", "rentals.override.report",
            "dashboard.view",
            "map.view",
            "markets.view",
            "markets.manage",
            "sections.view",
            "sections.manage",
            "stalls.view",
            "stalls.manage",
            "tenants.view",
            "tenants.manage",
            "assignments.view",
            "assignments.manage",
            "rates.view",
            "rates.manage",
            "billing.view",
            "billing.generate",
            "billing.post",
            "payments.view",
            "reports.view",
            "reports.financial",
            "reports.personal",
        ],
        "Market Staff" => [
            "rentals.override.view", "rentals.override.request", "rentals.override.history",
            "dashboard.view",
            "map.view",
            "markets.view",
            "sections.view",
            "stalls.view",
            "stalls.manage",
            "tenants.view",
            "tenants.manage",
            "assignments.view",
            "rates.view",
            "billing.view",
            "payments.view",
            "reports.view",
            "reports.personal",
        ],
        "Authorized Collection Officer" => [
            "dashboard.view",
            "map.view",
            "markets.view",
            "sections.view",
            "stalls.view",
            "tenants.view",
            "assignments.view",
            "rates.view",
            "billing.view",
            "payments.view",
            "payments.record",
            "payments.post",
            "reports.view",
            "reports.financial",
            "reports.personal",
        ],
        "Treasury/Accounting Officer" => [
            "rentals.override.view", "rentals.override.history", "rentals.override.report",
            "dashboard.view",
            "map.view",
            "markets.view",
            "sections.view",
            "stalls.view",
            "tenants.view",
            "assignments.view",
            "rates.view",
            "billing.view",
            "billing.adjust",
            "payments.view",
            "payments.reverse",
            "payments.reconcile",
            "reports.view",
            "reports.financial",
            "reports.personal",
            "audit.view",
            "financial.backdate",
        ],
        "LGU Management Viewer" => [
            "dashboard.view",
            "map.view",
            "markets.view",
            "sections.view",
            "stalls.view",
            "reports.view",
        ],
    ];
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        \Illuminate\Support\Facades\DB::transaction(function () {
            $extra = [
                "Super Administrator" => [
                    "tenants.view_sensitive",
                    "contracts.view",
                    "contracts.create",
                    "contracts.generate",
                    "contracts.download",
                    "contracts.print",
                    "contracts.approve",
                    "contracts.issue",
                    "contracts.void",
                    "contracts.upload_signed",
                    "contract_templates.manage",
                    "contract_templates.approve",
                ],
                "Market Administrator" => [
                    "tenants.view_sensitive",
                    "contracts.view",
                    "contracts.create",
                    "contracts.generate",
                    "contracts.download",
                    "contracts.print",
                    "contracts.upload_signed",
                ],
                "Market Staff" => ["contracts.view", "contracts.create"],
            ];
            $newPermissions = [];
            foreach (self::MATRIX as $role => $permissions) {
                foreach (array_unique(array_merge($permissions, $extra[$role] ?? [])) as $name) {
                    $permission = Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web']);
                    if ($permission->wasRecentlyCreated) {
                        $newPermissions[] = $name;
                    }
                }
            }
            foreach (self::MATRIX as $role => $permissions) {
                $record = Role::firstOrCreate(['name'=>$role,'guard_name'=>'web']);
                $defaults = array_unique(array_merge($permissions,$extra[$role] ?? []));
                // Existing role customizations survive deployment; grant only newly introduced permissions.
                $grants = $record->wasRecentlyCreated ? $defaults : array_intersect($defaults,$newPermissions);
                if ($grants) {
                    $record->givePermissionTo(array_values($grants));
                }
            }
        }, 3);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
