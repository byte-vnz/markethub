<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RentalOverride extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['request_hash', 'submission_key'];
    protected $casts = [
        'automatic_rate_per_sqm' => 'decimal:2', 'automatic_daily_rental' => 'decimal:2',
        'override_rate_per_sqm' => 'decimal:2', 'override_daily_amount' => 'decimal:2',
        'proposed_daily_rental' => 'decimal:2', 'rental_difference' => 'decimal:2', 'authority_snapshot' => 'array',
        'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'revoked_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(StallAssignment::class, 'stall_assignment_id');
    }

    public function initialAssessment()
    {
        return $this->belongsTo(RentalAssessment::class, 'initial_assessment_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopeCovering($query, string $start, ?string $end = null)
    {
        return $query->whereIn('status', ['approved', 'revoked'])->whereNotNull('approved_at')
            ->whereNotNull('approved_by')->when($end, fn ($q) => $q->where('effective_from', '<=', $end))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $start))
            ->where(fn ($q) => $q->whereNull('revocation_effective_from')->orWhere(function ($q) use ($start) {
                $q->where('revocation_effective_from', '>', $start)->whereColumn('revocation_effective_from', '>', 'effective_from');
            }));
    }

    public function lastCoveredDate(): ?string
    {
        if ($this->revocation_effective_from) {
            $last = Carbon::parse($this->revocation_effective_from)->subDay()->toDateString();
            return $this->effective_until ? min($last, $this->effective_until) : $last;
        }
        return $this->effective_until;
    }

    public function displayStatus($date = null): string
    {
        $date = Carbon::parse($date ?? today())->toDateString();
        if ($this->status === 'revoked' && $date >= $this->revocation_effective_from) {
            return 'revoked';
        }
        if (in_array($this->status, ['approved', 'revoked'], true)) {
            $termEnd = $this->assignment->end_date;
            return ($termEnd && $termEnd < $date) || ($this->effective_until && $this->effective_until < $date) ? 'expired' : 'approved';
        }
        return $this->status;
    }

    protected static function booted()
    {
        static::updating(function ($row) {
            $original = $row->getOriginal('status');
            if ($original === 'draft') {
                return;
            }
            $allowed = ['updated_at', 'updated_by', 'status'];
            if ($original === 'pending' && $row->status === 'approved') {
                $allowed = array_merge($allowed, ['approved_at', 'approved_by', 'rental_policy_id', 'authority_snapshot', 'approval_fingerprint']);
            } elseif ($original === 'pending' && $row->status === 'rejected') {
                $allowed = array_merge($allowed, ['rejected_at', 'rejected_by', 'rejection_reason']);
            } elseif ($original === 'approved' && $row->status === 'revoked') {
                $allowed = array_merge($allowed, ['revoked_at', 'revoked_by', 'revocation_effective_from', 'revocation_reason']);
            } else {
                throw new \LogicException('Submitted rental terms must be retained.');
            }
            if (array_diff(array_keys($row->getDirty()), $allowed)) {
                throw new \LogicException('Create a new request to change submitted terms.');
            }
        });
        static::deleting(function () {
            throw new \LogicException('Rental override history must be retained.');
        });
    }
}
