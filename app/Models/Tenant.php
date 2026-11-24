<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Tenant extends Model
{
    protected $guarded = ["id"];
    protected $hidden = ['government_id_number', 'registration_draft'];
    protected $casts = ['government_id_number' => 'encrypted', 'registration_draft' => 'encrypted:array'];
    public function helpers()
    {
        return $this->hasMany(TenantHelper::class);
    }
    public function getMaskedGovernmentIdNumberAttribute()
    {
        $number = $this->government_id_number;
        if (!$number) {
            return null;
        }
        return mb_strlen($number) > 4 ? '••••' . mb_substr($number, -4) : '••••';
    }
    public function businesses()
    {
        return $this->hasMany(Business::class);
    }
    public function contracts()
    {
        return $this->hasMany(LeaseContract::class);
    }
    public function assignments()
    {
        return $this->hasMany(StallAssignment::class);
    }
    public function bills()
    {
        return $this->hasMany(RentalBill::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
