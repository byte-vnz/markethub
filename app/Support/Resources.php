<?php
namespace App\Support;
use App\Models\{Market, MarketSection, StallCategory, Stall, Tenant, Business, RentalRate};
class Resources
{
    public static function all()
    {
        return [
            "markets" => [
                "label" => "Markets",
                "model" => Market::class,
                "permission" => "markets",
                "search" => "name",
                "fields" => [
                    "code" => ["Stall market code", "text"],
                    "name" => ["Market name", "text"],
                    "address" => ["Address", "text"],
                    "status" => [
                        "Status",
                        "select",
                        ["active" => "Active", "inactive" => "Inactive"],
                    ],
                ],
            ],
            "sections" => [
                "label" => "Sections",
                "model" => MarketSection::class,
                "permission" => "sections",
            ],
            "categories" => [
                "label" => "Stall categories",
                "model" => StallCategory::class,
                "permission" => "stalls",
                "search" => "name",
                "fields" => ["name" => ["Category name", "text"]],
            ],
            "stalls" => [
                "label" => "Stalls",
                "model" => Stall::class,
                "permission" => "stalls",
                "search" => "code",
                "fields" => [
                    "market_id" => ["Market", "relation", Market::class],
                    "market_section_id" => ["Section", "relation", MarketSection::class],
                    "stall_category_id" => ["Physical stall category", "relation", StallCategory::class],
                    "code" => ["Stall code", "text"],
                    "floor" => ["Floor / building", "text"],
                    "classification" => ["Physical classification / legacy rate group", "text"],
                    "area" => ["Area (sq. m)", "number"],
                    "location_type" => ["Stall location", "select", [''=>'Unclassified','inner'=>'Inner — minimum category rate','outer'=>'Outer — maximum category rate']],
                    "status" => [
                        "Operational status",
                        "select",
                        [
                            "vacant" => "Vacant",
                            "reserved" => "Reserved",
                            "maintenance" => "Under maintenance",
                            "inactive" => "Inactive",
                        ],
                    ],
                    "description" => ["Description", "textarea"],
                    "remarks" => ["Remarks", "textarea"],
                ],
            ],
            "tenants" => [
                "label" => "Tenants",
                "model" => Tenant::class,
                "permission" => "tenants",
                "search" => "name",
                "fields" => [
                    "reference" => ["Tenant reference", "text"],
                    "name" => ["Full legal name", "text"],
                    "phone" => ["Contact number", "text"],
                    "email" => ["Email", "email"],
                    "address" => ["Address", "textarea"],
                    "status" => [
                        "Status",
                        "select",
                        ["active" => "Active", "inactive" => "Inactive"],
                    ],
                    "remarks" => ["Remarks", "textarea"],
                ],
            ],
            "businesses" => [
                "label" => "Businesses",
                "model" => Business::class,
                "permission" => "tenants",
                "search" => "name",
                "fields" => [
                    "tenant_id" => ["Tenant", "relation", Tenant::class],
                    "name" => ["Business name", "text"],
                    "category" => ["Business category", "text"],
                    "description" => ["Description", "textarea"],
                    "permit_reference" => ["Permit reference", "text"],
                    "registration_reference" => ["Registration reference", "text"],
                    "permit_from" => ["Permit valid from", "date"],
                    "permit_until" => ["Permit valid until", "date"],
                    "status" => [
                        "Status",
                        "select",
                        ["active" => "Active", "inactive" => "Inactive"],
                    ],
                ],
            ],
            "rates" => [
                "label" => "Legacy monthly rental rates",
                "model" => RentalRate::class,
                "permission" => "rates",
                "search" => "classification",
                "fields" => [
                    "market_id" => ["Market", "relation", Market::class],
                    "classification" => ["Stall classification", "text"],
                    "amount" => ["Monthly amount (PHP)", "number"],
                    "effective_from" => ["Effective from", "date"],
                    "effective_until" => ["Effective until", "date"],
                    "approval_reference" => ["Approval reference", "text"],
                    "status" => [
                        "Status",
                        "select",
                        ["active" => "Active", "inactive" => "Inactive"],
                    ],
                ],
            ],
        ];
    }
    public static function get($type)
    {
        $all = self::all();
        abort_unless(isset($all[$type]), 404);
        return $all[$type];
    }
}
