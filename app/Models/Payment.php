<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model
{
    protected $guarded = ["id"];
    protected $casts = ["amount" => "decimal:2"];
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function officer()
    {
        return $this->belongsTo(User::class, "recorded_by");
    }
    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }
    public function reversal()
    {
        return $this->hasOne(PaymentReversal::class);
    }
    public function scopeEffective($q)
    {
        return $q->where("status", "posted")->whereDoesntHave("reversal");
    }
    protected static function booted()
    {
        static::updating(function ($m) {
            if (
                $m->getOriginal("status") === "posted" &&
                array_diff(array_keys($m->getDirty()), [
                    "reconciliation_status",
                    "reconciled_by",
                    "reconciled_at",
                    "active_receipt_reference",
                    "updated_at",
                ])
            ) {
                throw new \LogicException("Posted payments are immutable. Reverse instead.");
            }
        });
        static::deleting(function () {
            throw new \LogicException("Payments must be retained.");
        });
    }
}
