<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalAssessment extends Model
{
    protected $guarded = ["id"];
    protected $hidden = ["snapshot"];
    protected $casts = [
        "snapshot" => "encrypted:array",
        "basis" => "array",
        "billing_days" => "integer",
        "automatic_rate_per_sqm" => "decimal:2",
        "automatic_daily_rental" => "decimal:2",
        "rental_difference" => "decimal:2",
        "equivalent_rate_per_sqm" => "decimal:2",
        "contract_rate_consistent" => "boolean",
        "floor_area" => "decimal:2",
        "minimum_rate" => "decimal:2",
        "maximum_rate" => "decimal:2",
        "rental_rate_sqm" => "decimal:2",
        "daily_cusa" => "decimal:2",
        "daily_rental" => "decimal:2",
        "monthly_rental" => "decimal:2",
        "monthly_cusa" => "decimal:2",
        "total_monthly_rental_cusa" => "decimal:2",
        "advance_rental" => "decimal:2",
        "security_deposit" => "decimal:2",
        "total_initial_payment" => "decimal:2",
    ];

    public function assignment()
    {
        return $this->belongsTo(StallAssignment::class, "stall_assignment_id");
    }

    protected static function booted()
    {
        static::updating(function () {
            throw new \LogicException("Rental assessments are immutable.");
        });
        static::deleting(function () {
            throw new \LogicException("Rental assessments must be retained.");
        });
    }
}
