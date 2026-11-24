<?php

namespace App\Services;

use App\Models\{BusinessCategory, BusinessCategoryRentalRate, Market, RentalOverride, RentalPolicy, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BusinessCategoryService
{
    public static function scheduleRules(bool $policy, bool $approving = false): array
    {
        $decimal = ['bail', 'required', 'regex:/\A[0-9]{1,12}(\.[0-9]{1,2})?\z/', function ($attribute, $value, $fail) {
            if (!is_string($value) && !is_int($value)) {
                $fail('Enter a decimal amount with up to two decimal places.');
            }
        }];
        $dates = [
            'effective_from' => [$approving ? 'required' : 'nullable', 'required_with:effective_until', 'date_format:Y-m-d'],
            'effective_until' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'reference_document' => ($approving ? 'required' : 'nullable').'|string|max:255',
            'remarks' => 'nullable|string|max:2000',
        ];
        if ($policy) {
            return $dates + [
                'daily_cusa' => $decimal,
                'billing_day_rule' => 'required|in:calendar,fixed',
                'fixed_billing_days' => 'nullable|required_if:billing_day_rule,fixed|prohibited_if:billing_day_rule,calendar|integer|between:1,366',
                'max_concurrent_assignments' => 'required|integer|between:1,65535',
                'advance_deposit_authorized' => 'required|boolean',
                'allow_out_of_range_overrides' => 'sometimes|boolean',
                'allow_zero_rent_overrides' => 'sometimes|boolean',
                'override_authority_reference' => ($approving ? 'required_if:allow_out_of_range_overrides,1|required_if:allow_zero_rent_overrides,1|' : '').'nullable|string|max:255',
            ];
        }
        return $dates + ['minimum_rate' => $decimal, 'maximum_rate' => $decimal];
    }

    public static function retirementRules(): array
    {
        return [
            'effective_until' => 'required|date_format:Y-m-d|after_or_equal:'.today()->toDateString(),
            'reason' => 'required|string|max:500',
        ];
    }

    public function saveCategory(User $actor, array $data, ?BusinessCategory $category = null): BusinessCategory
    {
        $this->authorize($actor, 'rates.manage');

        return DB::transaction(function () use ($actor, $data, $category) {
            $category = $category ? BusinessCategory::lockForUpdate()->findOrFail($category->id) : new BusinessCategory;
            $old = $category->getAttributes();
            $creating = !$category->exists;
            if ($creating) {
                $category->code = $data['code'];
                $category->created_by = $actor->id;
            }
            $category->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'sort_order' => $data['sort_order'],
                'is_active' => $data['is_active'],
                'updated_by' => $actor->id,
            ])->save();

            Audit::record($actor, $creating ? 'business_category.created' : 'business_category.updated', $category, $old, $category->getChanges() ?: $category->getAttributes());
            return $category;
        }, 3);
    }

    public function saveSchedule(User $actor, Model $schedule, array $data): void
    {
        $this->authorize($actor, 'rates.manage');
        $policy = $schedule instanceof RentalPolicy;
        $data = Validator::make($data, self::scheduleRules($policy))->validate() + [
            'effective_from' => null, 'effective_until' => null, 'reference_document' => null, 'remarks' => null,
        ];
        if ($policy) {
            $data += ['fixed_billing_days' => null, 'allow_out_of_range_overrides' => false,
                'allow_zero_rent_overrides' => false, 'override_authority_reference' => null];
        }
        if (!$policy) {
            $this->validateRateRange($data);
        }

        DB::transaction(function () use ($actor, $schedule, $data) {
            $schedule = $this->lockSchedule($schedule);
            if (($schedule->exists && $schedule->status !== 'draft') || $schedule->approved_at) {
                throw ValidationException::withMessages(['status' => 'Only a draft can be edited. Create a new revision to change approved terms.']);
            }

            $old = $schedule->getAttributes();
            if (!$schedule->exists) {
                $schedule->created_by = $actor->id;
                $schedule->status = 'draft';
            }
            $schedule->fill($data)->save();
            Audit::record($actor, $this->auditAction($schedule, 'draft_saved'), $schedule, $old, $schedule->getChanges() ?: $schedule->getAttributes());
        }, 3);
    }

    public static function draftFingerprint(Model $schedule): string
    {
        $attributes = $schedule->getRawOriginal();
        ksort($attributes);
        return hash('sha256', serialize([get_class($schedule), $attributes]));
    }

    public function approve(User $actor, Model $schedule, string $draftFingerprint): void
    {
        $this->authorize($actor, 'rates.approve');

        DB::transaction(function () use ($actor, $schedule, $draftFingerprint) {
            $schedule = $this->lockSchedule($schedule);
            if ($schedule->status !== 'draft' || $schedule->approved_at) {
                throw ValidationException::withMessages(['status' => 'Only a draft can be approved.']);
            }
            if (!hash_equals(self::draftFingerprint($schedule), $draftFingerprint)) {
                throw ValidationException::withMessages(['draft_fingerprint' => 'This draft changed. Review the latest terms before approving.']);
            }

            $policy = $schedule instanceof RentalPolicy;
            $terms = [
                'effective_from' => optional($schedule->effective_from)->toDateString(),
                'effective_until' => optional($schedule->effective_until)->toDateString(),
            ] + ($policy ? ['daily_cusa' => $schedule->daily_cusa] : [
                'minimum_rate' => $schedule->minimum_rate, 'maximum_rate' => $schedule->maximum_rate,
            ]);
            $data = Validator::make($terms + $schedule->getAttributes(), self::scheduleRules($policy, true))->validate();
            if (!$policy) {
                $this->validateRateRange($data);
            }

            $parentKey = $policy ? 'market_id' : 'business_category_id';
            $overlap = $schedule->newQuery()->where($parentKey, $schedule->{$parentKey})
                ->whereIn('status', ['approved', 'retired'])
                ->where(function ($query) use ($data) {
                    $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']);
                });
            if (!empty($data['effective_until'])) {
                $overlap->whereDate('effective_from', '<=', $data['effective_until']);
            }
            // A current read after taking the parent lock sees approvals committed by earlier writers.
            if ($overlap->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['effective_from' => 'An approved revision overlaps this effective period. Close the prior revision before approving its successor.']);
            }

            $old = $schedule->getAttributes();
            $schedule->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            Audit::record($actor, $this->auditAction($schedule, 'approved'), $schedule, $old, $schedule->getAttributes());
        }, 3);
    }

    public function retire(User $actor, Model $schedule, array $data): void
    {
        $this->authorize($actor, 'rates.approve');
        $data = Validator::make($data, self::retirementRules())->validate();

        DB::transaction(function () use ($actor, $schedule, $data) {
            $schedule = $this->lockSchedule($schedule);
            if ($schedule->status !== 'approved' || !$schedule->approved_at || !$schedule->effective_from) {
                throw ValidationException::withMessages(['status' => 'Only a published revision can be retired.']);
            }

            $end = $data['effective_until'];
            if ($end < $schedule->effective_from->toDateString() || ($schedule->effective_until && $end > $schedule->effective_until->toDateString())) {
                throw ValidationException::withMessages(['effective_until' => 'Choose a last effective date inside the approved period.']);
            }

            $assessmentKey = $schedule instanceof RentalPolicy ? 'rental_policy_id' : 'business_category_rental_rate_id';
            $used = DB::table('rental_assessments')->where($assessmentKey, $schedule->id)
                ->where('purpose', 'initial')->whereDate('effective_from', '>', $end)
                ->lockForUpdate()->first(['id']);
            if ($used) {
                throw ValidationException::withMessages(['effective_until' => 'An agreement assessment already uses this revision after the proposed last effective date.']);
            }
            if ($schedule instanceof RentalPolicy && $this->requiresOverrideCoverage($schedule, $end)) {
                throw ValidationException::withMessages(['effective_until' => 'An approved rental override requires coverage beyond the proposed retirement date.']);
            }

            $old = $schedule->getAttributes();
            $schedule->update(['status' => 'retired', 'effective_until' => $end]);
            Audit::record($actor, $this->auditAction($schedule, 'retired'), $schedule, $old, $schedule->getAttributes(), $data['reason']);
        }, 3);
    }

    private function lockSchedule(Model $schedule): Model
    {
        if ($schedule instanceof RentalPolicy) {
            Market::lockForUpdate()->findOrFail($schedule->market_id);
        } else {
            BusinessCategory::lockForUpdate()->findOrFail($schedule->business_category_id);
        }
        return $schedule->exists ? $schedule->newQuery()->lockForUpdate()->findOrFail($schedule->id) : clone $schedule;
    }

    private function requiresOverrideCoverage(RentalPolicy $policy, string $end): bool
    {
        $next = \Carbon\Carbon::parse($end)->addDay();
        $overrides = RentalOverride::where('rental_policy_id', $policy->id);
        $after = (clone $overrides)->covering($next->toDateString())->whereHas('assignment', function ($q) use ($end) {
            $q->whereNull('end_date')->orWhere(function ($q) use ($end) {
                $q->where('end_date', '>', $end)->whereColumn('stall_assignments.end_date', '>=', 'rental_overrides.effective_from');
            });
        });
        if ($after->lockForUpdate()->first(['id'])) {
            return true;
        }
        // Ending an assignment limits future overrides, but cannot release a frozen reference month or lease term.
        $assessments = DB::table('rental_assessments')->whereIn('rental_override_id', $overrides->select('id'));
        if ((clone $assessments)->where('period', '>=', $next->format('Y-m'))->lockForUpdate()->first(['id'])) {
            return true;
        }
        return (bool) DB::table('lease_contracts')->whereIn('rental_assessment_id', $assessments->select('id'))
            ->whereNotIn('status', ['void', 'terminated'])->where('end_date', '>', $end)->lockForUpdate()->first(['id']);
    }

    private function validateRateRange(array $data): void
    {
        if (bccomp((string) $data['minimum_rate'], '0', 2) <= 0) {
            throw ValidationException::withMessages(['minimum_rate' => 'The minimum rate must be greater than zero.']);
        }
        if (bccomp((string) $data['maximum_rate'], (string) $data['minimum_rate'], 2) < 0) {
            throw ValidationException::withMessages(['maximum_rate' => 'The maximum rate must be at least the minimum rate.']);
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        abort_unless($actor->active && $actor->can($permission), 403);
    }

    private function auditAction(Model $schedule, string $action): string
    {
        return ($schedule instanceof RentalPolicy ? 'rental_policy.' : 'business_category_rate.').$action;
    }
}
