<?php
namespace App\Support;

use Illuminate\Http\Request;
use App\Models\User;

class Navigation
{
    public static function groups(User $user, Request $request)
    {
        $groups = [];
        foreach (config("navigation") as $group) {
            $items = [];
            foreach ($group["items"] as $item) {
                if (!$user->can($item["permission"])) {
                    continue;
                }
                $item["current"] = self::isCurrent($item, $request);
                $item["url"] = route($item["route"], $item["params"]);
                $items[] = $item;
            }
            if ($items) {
                $group["items"] = $items;
                $group["current"] =
                    count(
                        array_filter($items, function ($item) {
                            return $item["current"];
                        }),
                    ) > 0;
                $groups[] = $group;
            }
        }
        return $groups;
    }

    private static function isCurrent(array $item, Request $request)
    {
        $route = $request->route();
        if (!$route) {
            return false;
        }
        if ($item["route"] === "records.index" && ($item["params"]["type"] ?? null) === "tenants" && $request->routeIs('tenant-businesses.*','tenants.rental-preview','rental-overrides.*')) {
            return true;
        }
        if ($item["route"] === "payments.index" && $route->getName() === "payments.create") {
            return false;
        }
        if ($item["route"] === "reports.index" && $route->getName() === "reports.show") {
            return !in_array(
                $route->parameter("type"),
                ["occupied-stalls", "collection-summary", "tenant-master", "business-categories", "rental-assessments", "rental-overrides", "lease-contract-registry"],
                true,
            );
        }
        foreach ($item["active"] as $pattern) {
            if ($request->routeIs($pattern)) {
                foreach ($item["params"] as $key => $value) {
                    if ((string) $route->parameter($key) !== (string) $value) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }
}
