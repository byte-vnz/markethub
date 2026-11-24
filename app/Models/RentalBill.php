<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RentalBill extends Model
{
    protected $guarded = ["id"];
    protected $casts = ["amount" => "decimal:2"];
    public function assessment()
    {
        return $this->belongsTo(RentalAssessment::class, "rental_assessment_id");
    }
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function stall()
    {
        return $this->belongsTo(Stall::class);
    }
    public function assignment()
    {
        return $this->belongsTo(StallAssignment::class, "stall_assignment_id");
    }
    public function items()
    {
        return $this->hasMany(RentalBillItem::class);
    }
    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
    public function adjustments()
    {
        return $this->hasMany(BillingAdjustment::class);
    }
    public function applied()
    {
        return (string) $this->allocations()
            ->whereHas("payment", function ($q) {
                $q->where("status", "posted")->whereDoesntHave("reversal");
            })
            ->sum("amount");
    }
    public function total()
    {
        return bcadd($this->amount, (string) $this->adjustments()->sum("amount"), 2);
    }
    public function balance()
    {
        return $this->status === "posted" ? bcsub($this->total(), $this->applied(), 2) : "0.00";
    }
    public function billingStatus()
    {
        if ($this->status !== "posted") {
            return ucfirst($this->status);
        }
        $balance = $this->balance();
        if (bccomp($balance, "0", 2) === 0) {
            return "Paid";
        }
        if ($this->due_date < today()->toDateString()) {
            return "Overdue";
        }
        return bccomp($this->applied(), "0", 2) > 0 ? "Partially paid" : "Unpaid";
    }
    protected static function booted()
    {
        static::updating(function ($m) {
            if ($m->getOriginal("status") === "posted") {
                throw new \LogicException("Posted bills are immutable. Use an adjustment.");
            }
        });
        static::deleting(function () {
            throw new \LogicException("Bills must be retained.");
        });
    }
}
