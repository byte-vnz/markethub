<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BillingAdjustment extends Model
{
    protected $guarded = ["id"];
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
