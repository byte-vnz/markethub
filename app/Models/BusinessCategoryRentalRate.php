<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessCategoryRentalRate extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'minimum_rate' => 'decimal:2',
        'maximum_rate' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'approved_at' => 'datetime',
    ];

    public function businessCategory()
    {
        return $this->belongsTo(BusinessCategory::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
