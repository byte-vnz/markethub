<?php
namespace App\Policies;
use App\Models\User;
class RecordPolicy
{
    private function family($model)
    {
        $name = is_string($model) ? class_basename($model) : class_basename(get_class($model));
        return [
            "Market" => "markets",
            "MarketSection" => "sections",
            "MarketLayout" => "markets",
            "Stall" => "stalls",
            "StallCategory" => "stalls",
            "StallLayoutPosition" => "stalls",
            "Tenant" => "tenants",
            "Business" => "tenants",
            "StallAssignment" => "assignments",
            "RentalAgreement" => "assignments",
            "RentalRate" => "rates",
            "RentalBill" => "billing",
            "Payment" => "payments",
        ][$name] ?? "denied";
    }
    public function view(User $u, $m)
    {
        return $u->active && $u->can($this->family($m) . ".view");
    }
    public function update(User $u, $m)
    {
        return $u->active && $u->can($this->family($m) . ".manage");
    }
    public function delete(User $u, $m)
    {
        return $this->family($m) !== 'sections' && $this->update($u, $m);
    }
}
