<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessCategory extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }

    public function rentalRates()
    {
        return $this->hasMany(BusinessCategoryRentalRate::class);
    }
}
