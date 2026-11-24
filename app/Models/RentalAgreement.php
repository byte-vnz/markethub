<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RentalAgreement extends Model
{
    protected $guarded = ["id"];
    protected $casts = ["monthly_amount" => "decimal:2"];

    public function assessment()
    {
        return $this->belongsTo(RentalAssessment::class, "rental_assessment_id");
    }
    public function assignment()
    {
        return $this->belongsTo(StallAssignment::class, "stall_assignment_id");
    }
    public function rate()
    {
        return $this->belongsTo(RentalRate::class, "rental_rate_id");
    }

    protected static function booted()
    {
        static::saving(function (RentalAgreement $agreement) {
            if (($agreement->rental_rate_id === null) === ($agreement->rental_assessment_id === null)) {
                throw new \LogicException("A rental agreement requires exactly one basis.");
            }
        });
        static::updating(function (RentalAgreement $agreement) {
            if ($agreement->isDirty(["rental_rate_id", "rental_assessment_id", "monthly_amount",
                "stall_assignment_id", "start_date", "reference"])) {
                throw new \LogicException("The recorded rental agreement basis is immutable. Renew the assignment.");
            }
        });
    }
}
