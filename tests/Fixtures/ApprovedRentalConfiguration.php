<?php
namespace Tests\Fixtures;

use App\Models\{Business, BusinessCategory, BusinessCategoryRentalRate, RentalPolicy, Tenant, User};

trait ApprovedRentalConfiguration
{
    private function businessWithApprovedRental(User $actor, Tenant $tenant, $marketId): Business
    {
        $category = BusinessCategory::create(["code" => "TEST-FINANCIAL", "name" => "Fictional shop category"]);
        $approval = [
            "status" => "approved", "effective_from" => "2020-01-01",
            "reference_document" => "SYNTHETIC-TEST-REFERENCE",
            "created_by" => $actor->id, "approved_by" => $actor->id, "approved_at" => now(),
        ];
        BusinessCategoryRentalRate::create($approval + [
            "business_category_id" => $category->id, "minimum_rate" => "20.00", "maximum_rate" => "25.00",
        ]);
        RentalPolicy::create($approval + [
            "market_id" => $marketId, "daily_cusa" => "10.00", "billing_day_rule" => "fixed",
            "fixed_billing_days" => 30, "max_concurrent_assignments" => 1, "advance_deposit_authorized" => true,
        ]);
        return Business::create([
            "tenant_id" => $tenant->id, "name" => "Synthetic business",
            "business_category_id" => $category->id, "line_of_business" => "Synthetic trade",
            "permitted_goods" => "Synthetic permitted goods", "status" => "active",
        ]);
    }
}
