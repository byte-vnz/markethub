<?php

namespace App\Services;

use App\Models\{LeaseContract, Market, RentalAssessment, RentalBill, RentalOverride, RentalPolicy, StallAssignment, Tenant, User};
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

class RentalOverrideService
{
    private const TERMS = ['override_type', 'override_rate_per_sqm', 'override_daily_amount', 'effective_from',
        'effective_until', 'reason', 'reference_document', 'remarks', 'source_override_id'];

    private function authorize(User $actor, string $action): void
    {
        abort_unless($actor->active && $actor->can('rentals.override.'.$action), 403);
    }

    private function check($valid, string $field, string $message): void
    {
        if (!$valid) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function locked(StallAssignment $assignment, callable $operation)
    {
        return DB::transaction(function () use ($assignment, $operation) {
            $tenant = Tenant::whereKey($assignment->tenant_id)->lockForUpdate()->firstOrFail();
            $a = StallAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->check((int) $tenant->id === (int) $a->tenant_id, 'tenant_id', 'The assignment belongs to another tenant.');
            $a->setRelation('tenant', $tenant);
            $initial = optional($a->agreement()->lockForUpdate()->first())->assessment;
            $this->check($initial && $initial->purpose === 'initial' && (int) $initial->stall_assignment_id === (int) $a->id,
                'assessment', 'Renew this legacy assignment before requesting a daily rental override.');
            $this->check(!$initial->isFixedDays(), 'assessment', 'Monthly rental overrides are unavailable for fixed-day agreements.');
            // Policy approvals and retirement also take this market lock before inspecting dependent requests.
            Market::whereKey($initial->market_id)->lockForUpdate()->firstOrFail();
            return $operation($a, $initial);
        }, 3);
    }

    private function transition(RentalOverride $request, callable $operation): RentalOverride
    {
        return $this->locked(StallAssignment::findOrFail($request->stall_assignment_id), function ($a, $initial) use ($request, $operation) {
            $current = RentalOverride::where('stall_assignment_id', $a->id)->lockForUpdate()->findOrFail($request->id);
            $this->check((int) $current->initial_assessment_id === (int) $initial->id, 'assessment', 'The agreement basis has changed.');
            return $operation($current, $a, $initial);
        });
    }

    private function eligible(StallAssignment $a, RentalAssessment $initial): void
    {
        $this->check($a->status === 'active' && $a->tenant->status === 'active' && $a->business &&
            $a->business->status === 'active' && (int) $a->business->tenant_id === (int) $a->tenant_id &&
            (int) $a->business_id === (int) $initial->business_id, 'stall_assignment_id', 'Choose an active assignment with its assessed business and tenant.');
    }

    private function checkOwnership(StallAssignment $a, array $input): void
    {
        foreach (['tenant_id' => $a->tenant_id, 'business_id' => $a->business_id, 'stall_id' => $a->stall_id, 'stall_assignment_id' => $a->id] as $key => $id) {
            $this->check(!array_key_exists($key, $input) || ((is_string($input[$key]) || is_int($input[$key])) && (string) $input[$key] === (string) $id),
                $key, 'The selected record does not belong to this assignment.');
        }
    }

    private function dates(StallAssignment $a, string $start, ?string $end): void
    {
        $this->boundary($a, $start, 'effective_from');
        $this->check(!$end || $end === Carbon::parse($end)->endOfMonth()->toDateString(), 'effective_until', 'Choose the last day of a full month.');
        $this->check(!$a->end_date || ($end && $end <= $a->end_date), 'effective_until', 'End the override within the assignment term.');
    }

    private function boundary(StallAssignment $a, string $day, string $field): void
    {
        $first = today()->day === 1 ? today() : today()->addMonthNoOverflow()->startOfMonth();
        $this->check(substr($day, -2) === '01' && $day >= $first->toDateString(), $field,
            'Choose a prospective first of the month, beginning '.$first->toDateString().'.');
        $this->check($day >= $a->start_date && (!$a->end_date || $day <= $a->end_date), $field, 'Choose a month within the assignment term.');
    }

    public function preview(User $actor, StallAssignment $assignment, array $data): array
    {
        $this->authorize($actor, 'request');
        return $this->locked($assignment, function ($a, $initial) use ($data) {
            $this->checkOwnership($a, $data);
            $this->eligible($a, $initial);
            Validator::make($data, ['period' => 'required|date_format:Y-m', 'override_type' => 'required|in:rate_per_sqm,daily_amount'])->validate();
            app(RentalCalculationService::class)->guardReferenceMonth($a, $data['period']);
            return ['computation_mode' => 'proposed', 'rental_override_id' => null] + app(RentalCalculationService::class)
                ->proposedAmounts($initial, $data['period'], $data['override_type'], $data['override_rate_per_sqm'] ?? null, $data['override_daily_amount'] ?? null);
        });
    }

    public function saveDraft(User $actor, StallAssignment $assignment, array $data, ?RentalOverride $override = null): RentalOverride
    {
        $this->authorize($actor, 'request');
        if (isset($data['effective_until']) && is_string($data['effective_until']) && trim($data['effective_until']) === '') {
            $data['effective_until'] = null;
        }
        Validator::make($data, [
            'submission_key' => ($override ? 'sometimes' : 'required').'|uuid',
            'override_type' => 'required|in:rate_per_sqm,daily_amount', 'effective_from' => 'required|date_format:Y-m-d',
            'effective_until' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'reason' => 'required|string|max:1000', 'remarks' => 'nullable|string|max:2000', 'reference_document' => 'nullable|string|max:255',
            'source_override_id' => 'nullable|integer|min:1', 'draft_fingerprint' => 'sometimes|string|size:64',
        ])->validate();
        return $this->locked($assignment, function ($a, $initial) use ($actor, $data, $override) {
            $this->checkOwnership($a, $data);
            $terms = Arr::only($data, self::TERMS) + array_fill_keys(self::TERMS, null);
            foreach (['reason', 'reference_document', 'remarks'] as $field) {
                $terms[$field] = isset($terms[$field]) ? trim($terms[$field]) : null;
            }
            $values = $this->amountsForTerm($initial, $terms);
            $field = $terms['override_type'] === 'daily_amount' ? 'override_daily_amount' : 'override_rate_per_sqm';
            $terms[$field] = Money::normalize($terms[$field]);
            $terms['source_override_id'] = $terms['source_override_id'] ? (int) $terms['source_override_id'] : null;
            ksort($terms);
            $hash = hash('sha256', serialize([$a->id, $actor->id, $terms]));
            if ($override) {
                $row = RentalOverride::where('stall_assignment_id', $a->id)->lockForUpdate()->findOrFail($override->id);
                abort_unless((int) $row->requested_by === (int) $actor->id, 403);
                $this->check($row->status === 'draft', 'status', 'Only a draft may be edited. Create a new request for revised terms.');
                $this->check(!isset($data['submission_key']) || $data['submission_key'] === $row->submission_key, 'submission_key', 'Keep the original draft identity.');
                $this->check($terms['source_override_id'] === ($row->source_override_id ? (int) $row->source_override_id : null),
                    'source_override_id', 'The source of this revision cannot change.');
                if ($row->request_hash === $hash) {
                    return $row;
                }
                $this->check(hash_equals($row->request_hash, $override->request_hash) &&
                    (!isset($data['draft_fingerprint']) || hash_equals($data['draft_fingerprint'], self::fingerprint($row))),
                    'draft_fingerprint', 'Review the latest draft before saving changes.');
            } else {
                $row = RentalOverride::where('submission_key', $data['submission_key'])->lockForUpdate()->first();
                if ($row) {
                    $this->check(hash_equals($row->request_hash, $hash), 'submission_key', 'This submission key belongs to a different request.');
                    return $row;
                }
                if ($terms['source_override_id']) {
                    $this->check(RentalOverride::where('stall_assignment_id', $a->id)->whereKey($terms['source_override_id'])
                        ->where('status', '!=', 'draft')->lockForUpdate()->first(), 'source_override_id', 'Select a submitted source request for this assignment.');
                }
                $last = RentalOverride::where('stall_assignment_id', $a->id)->orderByDesc('revision')->lockForUpdate()->first();
                $row = new RentalOverride(['stall_assignment_id' => $a->id, 'initial_assessment_id' => $initial->id,
                    'submission_key' => $data['submission_key'], 'revision' => $last ? $last->revision + 1 : 1,
                    'requested_by' => $actor->id, 'status' => 'draft']);
            }
            $this->eligible($a, $initial);
            $this->dates($a, $terms['effective_from'], $terms['effective_until']);
            $old = $row->getRawOriginal();
            $row->fill($terms + Arr::only($values, ['automatic_rate_per_sqm','automatic_daily_rental','rental_difference']) +
                ['proposed_daily_rental' => $values['daily_rental'], 'updated_by' => $actor->id, 'request_hash' => $hash])->save();
            Audit::record($actor, $row->wasRecentlyCreated && $row->source_override_id ? 'rental_override.revised' : 'rental_override.draft_saved',
                $row, $old, $row->getAttributes(), $row->reason);
            return $row->refresh();
        });
    }

    public function submit(User $actor, RentalOverride $override): RentalOverride
    {
        $this->authorize($actor, 'request');
        return $this->transition($override, function ($row, $a, $initial) use ($actor, $override) {
            abort_unless((int) $row->requested_by === (int) $actor->id, 403);
            $this->check(hash_equals($row->request_hash, $override->request_hash), 'draft_fingerprint', 'Review the changed draft before submission.');
            if ($row->status === 'pending') {
                return $row;
            }
            $this->check($row->status === 'draft', 'status', 'Only a draft may be submitted.');
            $this->eligible($a, $initial);
            $this->dates($a, $row->effective_from, $row->effective_until);
            return $this->record($actor, $row, ['status' => 'pending', 'submitted_at' => now()], 'submitted', $row->reason);
        });
    }

    // Only a single approved revision may cover any part of the requested interval.
    public static function approvalPolicy(RentalOverride $override): ?RentalPolicy
    {
        $query = RentalPolicy::where('market_id', $override->initialAssessment->market_id)
            ->whereIn('status', ['approved','retired'])->whereNotNull('approved_at')->whereNotNull('approved_by')
            ->whereNotNull('effective_from')->when($override->effective_until, fn ($q, $end) => $q->whereDate('effective_from', '<=', $end))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $override->effective_from));
        $matches = (DB::transactionLevel() ? $query->lockForUpdate() : $query)->limit(2)->get();
        $p = $matches->first();
        if ($matches->count() !== 1 || $p->status !== 'approved' || trim((string) $p->reference_document) === '' ||
            $p->effective_from->toDateString() > $override->effective_from ||
            ($p->effective_until && (!$override->effective_until || $p->effective_until->toDateString() < $override->effective_until))) {
            return null;
        }
        return $p;
    }

    private static function policyTerms(RentalPolicy $policy): array
    {
        $data = $policy->only(['id', 'market_id', 'allow_out_of_range_overrides', 'allow_zero_rent_overrides',
            'override_authority_reference', 'reference_document', 'approved_by', 'daily_cusa', 'billing_day_rule',
            'fixed_billing_days', 'max_concurrent_assignments', 'advance_deposit_authorized']);
        $data['approved_at'] = $policy->approved_at->toDateTimeString();
        $data['effective_from'] = $policy->effective_from->toDateString();
        $data['effective_until'] = optional($policy->effective_until)->toDateString();
        return $data;
    }

    public static function fingerprint(RentalOverride $override): string
    {
        return self::review($override)['fingerprint'];
    }

    public static function review(RentalOverride $override): array
    {
        $policy = self::approvalPolicy($override);
        $basis = $override->initialAssessment;
        $terms = $policy ? self::policyTerms($policy) : null;
        $fingerprint = hash('sha256', serialize([$override->id, $override->revision, $override->request_hash, $override->status,
            $override->only(self::TERMS), $override->requested_by, $override->automatic_rate_per_sqm, $override->automatic_daily_rental,
            $override->proposed_daily_rental, $override->rental_difference,
            $basis->id, $basis->floor_area, $basis->minimum_rate, $basis->maximum_rate, $basis->daily_rental, $basis->basis,
            $terms]));
        return ['policy' => $terms, 'fingerprint' => $fingerprint];
    }

    public function approve(User $actor, RentalOverride $override, string $fingerprint): RentalOverride
    {
        $this->authorize($actor, 'approve');
        return $this->transition($override, function ($row, $a, $initial) use ($actor, $fingerprint) {
            if ($row->status === 'approved' && (int) $row->approved_by === (int) $actor->id && hash_equals($row->approval_fingerprint, $fingerprint)) {
                return $row;
            }
            $this->check($row->status === 'pending', 'status', 'Only a pending request may be approved.');
            $this->check((int) $row->requested_by !== (int) $actor->id, 'approved_by', 'A different authorized user must review and approve the request.');
            $this->eligible($a, $initial);
            $this->dates($a, $row->effective_from, $row->effective_until);
            $review = self::review($row);
            $this->check(hash_equals($review['fingerprint'], $fingerprint), 'fingerprint', 'Review the current request, assessment and effective policy before approval.');
            $policy = $review['policy'];
            $this->check($policy, 'rental_policy', 'One approved policy must cover the entire requested period. Open-ended requests require open-ended authority.');
            $value = $this->amountsForTerm($initial, $row->only(self::TERMS));
            if ($value['out_of_range'] || $value['zero_rent']) {
                $this->check(trim((string) $row->reference_document) !== '' && trim((string) $policy['override_authority_reference']) !== '' &&
                    (!$value['out_of_range'] || $policy['allow_out_of_range_overrides']) && (!$value['zero_rent'] || $policy['allow_zero_rent_overrides']),
                    'override_authority', 'The proposed exception requires explicit policy authorization and both policy and request references.');
            }
            $this->check(!RentalOverride::where('stall_assignment_id', $a->id)->covering($row->effective_from, $row->effective_until)->lockForUpdate()->first(),
                'effective_from', 'An approved rental already covers part of this period.');
            $this->unassessed($a, $row->effective_from, $row->effective_until);
            return $this->record($actor, $row, ['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id,
                'rental_policy_id' => $policy['id'], 'authority_snapshot' => $policy, 'approval_fingerprint' => $fingerprint],
                'approved', $row->reason);
        });
    }

    public function reject(User $actor, RentalOverride $override, string $reason): RentalOverride
    {
        $this->authorize($actor, 'reject');
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|max:1000'])->validate();
        return $this->transition($override, function ($row) use ($actor, $reason) {
            if ($row->status === 'rejected' && (int) $row->rejected_by === (int) $actor->id && $row->rejection_reason === $reason) {
                return $row;
            }
            $this->check($row->status === 'pending', 'status', 'Only a pending request may be rejected.');
            return $this->record($actor, $row, ['status' => 'rejected', 'rejected_by' => $actor->id, 'rejected_at' => now(),
                'rejection_reason' => $reason], 'rejected', $reason);
        });
    }

    private function amountsForTerm(RentalAssessment $initial, array $terms): array
    {
        $month = Carbon::parse($terms['effective_from']);
        // Every month shorter than 31 days is immediately followed by a 31-day month.
        if ($initial->basis['billing_day_rule'] === 'calendar' && $month->daysInMonth < 31 &&
            (!$terms['effective_until'] || substr($terms['effective_until'], 0, 7) > $month->format('Y-m'))) {
            $month->addMonthNoOverflow();
        }
        return app(RentalCalculationService::class)->proposedAmounts($initial, $month->format('Y-m'),
            $terms['override_type'], $terms['override_rate_per_sqm'], $terms['override_daily_amount']);
    }

    public function revoke(User $actor, RentalOverride $override, array $data): RentalOverride
    {
        $this->authorize($actor, 'revoke');
        $data = Validator::make($data, ['revocation_effective_from' => 'required|date_format:Y-m-d',
            'reason' => 'required|string|max:1000'])->validate();
        return $this->transition($override, function ($row, $a) use ($actor, $data) {
            $day = $data['revocation_effective_from'];
            $reason = trim($data['reason']);
            if ($row->status === 'revoked' && (int) $actor->id === (int) $row->revoked_by && $row->revocation_effective_from === $day && $row->revocation_reason === $reason) {
                return $row;
            }
            $this->check($row->status === 'approved', 'status', 'Only an approved rental may return to automatic.');
            $this->boundary($a, $day, 'revocation_effective_from');
            $this->check(!$row->effective_until || $day <= $row->effective_until, 'revocation_effective_from', 'Choose a boundary before the override expires.');
            $this->unassessed($a, max($row->effective_from, $day), $row->effective_until);
            return $this->record($actor, $row, ['status' => 'revoked', 'revoked_by' => $actor->id, 'revoked_at' => now(),
                'revocation_effective_from' => $day, 'revocation_reason' => $reason], 'revoked', $reason);
        });
    }

    private function record(User $actor, RentalOverride $row, array $values, string $event, string $reason): RentalOverride
    {
        $old = $row->getRawOriginal();
        $row->update($values + ['updated_by' => $actor->id]);
        Audit::record($actor, 'rental_override.'.$event, $row, $old, $row->getAttributes(), $reason);
        return $row->refresh();
    }

    private function unassessed(StallAssignment $a, string $start, ?string $end): void
    {
        $periods = function ($q) use ($a, $start, $end) {
            return $q->where('stall_assignment_id', $a->id)->where('period', '>=', substr($start, 0, 7))
                ->when($end, fn ($q) => $q->where('period', '<=', substr($end, 0, 7)))->lockForUpdate()->first(['id']);
        };
        $this->check(!$periods(RentalAssessment::whereIn('purpose', ['billing','contract'])) && !$periods(RentalBill::query()),
            'period', 'A saved assessment or bill protects this month. Choose a later unassessed period.');
        $contract = LeaseContract::where('stall_assignment_id', $a->id)->whereNotIn('status', ['void', 'terminated'])
            ->where('end_date', '>=', $start)->when($end, fn ($q) => $q->where('start_date', '<=', $end))->lockForUpdate()->first(['id']);
        $this->check(!$contract, 'contract', 'An existing contract protects this term. Choose a period outside its obligations.');
    }

    public function guardContractTerms(StallAssignment $assignment, string $start, string $end, ?RentalAssessment $assessment = null): void
    {
        $matches = RentalOverride::where('stall_assignment_id', $assignment->id)->covering($start, $end)->lockForUpdate()->limit(2)->get();
        $override = $matches->first();
        $this->check(!$override || ($matches->count() === 1 && $override->effective_from <= $start &&
            (!$override->lastCoveredDate() || $override->lastCoveredDate() >= $end)), 'contract', 'The contract must fit one constant approved rental interval.');
        if ($assessment) {
            $this->check((int) $assessment->rental_override_id === (int) optional($override)->id, 'contract', 'The saved contract assessment differs from the approved term.');
            $this->check($assessment->contract_rate_consistent, 'contract', 'The two-decimal printed rate cannot reproduce the approved daily rent. Choose a consistent approved arrangement.');
        }
    }
}
