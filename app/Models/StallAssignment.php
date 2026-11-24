<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StallAssignment extends Model
{
    protected $guarded = ["id"];
    public function stall()
    {
        return $this->belongsTo(Stall::class);
    }
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    public function agreement()
    {
        return $this->hasOne(RentalAgreement::class);
    }
    public function rentalOverrides()
    {
        return $this->hasMany(RentalOverride::class);
    }
    public function bills()
    {
        return $this->hasMany(RentalBill::class);
    }
}
