<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalPolicy extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'daily_cusa' => 'decimal:2',
        'fixed_billing_days' => 'integer',
        'max_concurrent_assignments' => 'integer',
        'advance_deposit_authorized' => 'boolean',
        'allow_out_of_range_overrides' => 'boolean',
        'allow_zero_rent_overrides' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'approved_at' => 'datetime',
    ];

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
