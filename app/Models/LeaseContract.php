<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LeaseContract extends Model
{
    protected $guarded = ["id"];
    protected $hidden = [
        "snapshot",
        "docx_path",
        "pdf_path",
        "signed_path",
        "request_hash",
        "submission_key",
    ];
    protected $casts = [
        "snapshot" => "encrypted:array",
        "floor_area" => "decimal:2",
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
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function assessment()
    {
        return $this->belongsTo(RentalAssessment::class, "rental_assessment_id");
    }
    public function assignment()
    {
        return $this->belongsTo(StallAssignment::class, "stall_assignment_id");
    }
    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, "contract_template_id");
    }
    protected static function booted()
    {
        static::updating(function ($model) {
            if ($model->getOriginal("signed_path")) {
                foreach (["signed_path", "signed_checksum", "signed_at"] as $key) {
                    if ($model->isDirty($key)) {
                        throw new \LogicException("The executed signed copy is immutable.");
                    }
                }
            }
            if ($model->getOriginal("status") !== "draft") {
                foreach (
                    [
                        "tenant_id",
                        "stall_assignment_id",
                        "contract_template_id",
                        "rental_assessment_id",
                        "control_number",
                        "snapshot",
                        "start_date",
                        "end_date",
                        "execution_date",
                        "billing_days",
                        "floor_area",
                        "rental_rate_sqm",
                        "daily_cusa",
                        "daily_rental",
                        "monthly_rental",
                        "monthly_cusa",
                        "total_monthly_rental_cusa",
                        "advance_rental",
                        "security_deposit",
                        "total_initial_payment",
                        "permitted_goods",
                        "docx_path",
                        "docx_checksum",
                        "pdf_path",
                        "pdf_checksum",
                    ]
                    as $key
                ) {
                    if ($model->isDirty($key)) {
                        throw new \LogicException(
                            "Generated contract contents are immutable; void and reissue.",
                        );
                    }
                }
            }
        });
        static::deleting(function () {
            throw new \LogicException("Contract history cannot be deleted.");
        });
    }
}
