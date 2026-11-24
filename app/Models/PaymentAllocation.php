<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentAllocation extends Model
{
    protected $guarded = ["id"];
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
    public function bill()
    {
        return $this->belongsTo(RentalBill::class, "rental_bill_id");
    }
    protected static function booted()
    {
        static::updating(function () {
            throw new \LogicException("Financial detail records are immutable.");
        });
        static::deleting(function () {
            throw new \LogicException("Financial detail records must be retained.");
        });
    }
}
