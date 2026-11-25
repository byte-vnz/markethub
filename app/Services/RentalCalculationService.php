<?php
namespace App\Services;

use App\Models\{Business, BusinessCategoryRentalRate, Market, RentalAssessment, RentalOverride, RentalPolicy, Stall, StallAssignment, Tenant, User};
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

class RentalCalculationService
{
    private const ASSESSMENT_COLUMNS = [
        "business_id", "business_category_id", "business_category_rental_rate_id", "rental_policy_id",
        "market_id", "market_name", "stall_code", "business_category_name", "location_type",
        "minimum_rate", "maximum_rate", "floor_area", "rental_rate_sqm", "daily_cusa", "billing_days",
        "daily_rental", "monthly_rental", "monthly_cusa", "total_monthly_rental_cusa",
        "advance_rental", "security_deposit", "total_initial_payment", "computation_mode", "rental_override_id",
        "automatic_daily_rental", "automatic_rate_per_sqm", "rental_difference", "market_section_id", "market_section_name",
        "equivalent_rate_per_sqm", "contract_rate_consistent",
    ];

    public function resolve(Business $business, Stall $stall, $date, $period, array $terms = []): array
    {
        Validator::make(["effective_from" => $date], ["effective_from" => "required|date_format:Y-m-d"])->validate();
        $this->check($business->status === "active" && optional($business->tenant)->status === "active",
            "business_id", "Select an active business with an active tenant.");
        $this->check(in_array($stall->location_type, ["inner", "outer"], true),
            "location_type", "Classify the stall as Inner or Outer before assessment.");

        $marketQuery = Market::whereKey($stall->market_id);
        $categoryQuery = $business->businessCategory();
        if (DB::transactionLevel() > 0) {
            $marketQuery->lockForUpdate();
            $categoryQuery->lockForUpdate();
        }
        $market = $marketQuery->first();
        $category = $categoryQuery->first();
        $this->check(optional($market)->status === "active" && optional($stall->section)->status === "active",
            "stall_id", "Market and section must be active.");
        $this->check($category && $category->is_active,
            "business_category_id", "Select a business with an active business category.");

        $rate = $this->approved(
            BusinessCategoryRentalRate::where("business_category_id", $category->id),
            $date,
            "category_rate",
            "Choose a category with exactly one approved rate effective on the agreement start.",
        );
        $fixed = (bool) config('billing.fixed_day_enabled', false);
        // Only AssignmentService supplies this already-validated concurrency authority.
        $policy = $fixed ? ($terms['concurrency_policy'] ?? null) : $this->marketPolicy($market->id, $date);
        if ($fixed) {
            $terms = Validator::make([
                'billing_interval_days' => $terms['billing_interval_days'] ?? config('billing.default_interval_days', 7),
            ], ['billing_interval_days' => 'required|integer|between:1,366'])->validate() + $terms;
        }
        $min = $this->decimal($rate->minimum_rate, "minimum_rate", "999999999999.99", true);
        $max = $this->decimal($rate->maximum_rate, "maximum_rate", "999999999999.99", true);
        $this->check(bccomp($min, $max, 2) <= 0, "category_rate", "The approved rate range is invalid.");
        $ratePerSqm = $stall->location_type === "inner" ? $min : $max;
        $basis = [
            "business_id" => $business->id,
            "business_category_id" => $category->id,
            "business_category_rental_rate_id" => $rate->id,
            "rental_policy_id" => optional($policy)->id,
            "market_id" => $market->id,
            "market_name" => $market->name,
            "stall_code" => $stall->code,
            "business_category_name" => $category->name,
            "location_type" => $stall->location_type,
            "minimum_rate" => $min,
            "maximum_rate" => $max,
            "billing_day_rule" => $fixed ? 'fixed' : $policy->billing_day_rule,
            "fixed_billing_days" => $fixed ? (int) $terms['billing_interval_days'] : $policy->fixed_billing_days,
            "max_concurrent_assignments" => optional($policy)->max_concurrent_assignments,
            "advance_deposit_authorized" => $fixed ? false : $policy->advance_deposit_authorized,
            "rate_reference" => $rate->reference_document,
            "rate_effective_from" => $rate->effective_from->toDateString(),
            "rate_effective_until" => optional($rate->effective_until)->toDateString(),
            "policy_reference" => optional($policy)->reference_document,
            "policy_effective_from" => $policy ? $policy->effective_from->toDateString() : null,
            "policy_effective_until" => $policy ? optional($policy->effective_until)->toDateString() : null,
            "stall_section_name" => $stall->section->name,
            "market_section_id" => $stall->market_section_id,
            "market_section_name" => $stall->section->name,
            "stall_category_name" => optional($stall->category)->name ?? $stall->classification,
        ];
        if ($fixed) {
            $basis['billing_cycle'] = 'fixed_days';
            $basis['cusa_chargeable'] = false;
        }
        $automatic = $this->calculate(
            (string) $stall->area,
            $ratePerSqm,
            $fixed ? ($terms['daily_cusa'] ?? '0.00') : $policy->daily_cusa,
            $fixed ? $basis['fixed_billing_days'] : $this->billingDays($basis, $period),
            $fixed,
        );
        return $basis + $automatic + [
            'computation_mode' => 'automatic', 'automatic_rate_per_sqm' => $ratePerSqm,
            'automatic_daily_rental' => $automatic['daily_rental'], 'rental_difference' => '0.00', 'contract_rate_consistent' => true,
        ];
    }

    public function marketPolicy($marketId, $date): RentalPolicy
    {
        $policy = $this->approved(
            RentalPolicy::where("market_id", $marketId),
            $date,
            "rental_policy",
            "The market requires exactly one approved rental policy effective on the agreement start.",
        );
        $this->check($policy->max_concurrent_assignments >= 1, "rental_policy",
            "The approved policy must specify a positive assignment limit.");
        return $policy;
    }

    private function approved($query, $date, $field, $message)
    {
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        $matches = $query->whereIn("status", ["approved", "retired"])
            ->whereNotNull("approved_at")->whereNotNull("approved_by")
            ->whereNotNull("reference_document")
            ->whereDate("effective_from", "<=", $date)
            ->where(function ($query) use ($date) {
                $query->whereDate("effective_until", ">=", $date)
                    ->orWhere(function ($query) {
                        $query->where("status", "approved")->whereNull("effective_until");
                    });
            })
            ->limit(2)->get();
        $this->check($matches->count() === 1 && trim((string) $matches->first()->reference_document) !== "",
            $field, $message);
        return $matches->first();
    }

    private function billingDays(array $basis, $period): int
    {
        Validator::make(["period" => $period], ["period" => "required|date_format:Y-m"])->validate();
        if ($basis["billing_day_rule"] === "calendar") {
            return Carbon::parse($period . "-01")->daysInMonth;
        }
        $days = $basis["fixed_billing_days"];
        $this->check($basis["billing_day_rule"] === "fixed" && is_int($days) && $days >= 1 && $days <= 366,
            "rental_policy", "The approved policy must specify calendar days or a valid fixed day count.");
        return $days;
    }

    public function fromAssessment(RentalAssessment $assessment, $period): array
    {
        $amounts = $this->compose(
            $assessment->floor_area,
            $assessment->rental_rate_sqm,
            $assessment->daily_cusa,
            $assessment->isFixedDays() ? (int) $assessment->basis['fixed_billing_days'] : $this->billingDays($assessment->basis, $period),
            $assessment->daily_rental,
            $assessment->isFixedDays(),
        );
        return $amounts;
    }

    public function forAssignment(StallAssignment $assignment, string $period): array
    {
        $assignment = StallAssignment::findOrFail($assignment->id);
        $initial = optional($assignment->agreement()->first())->assessment;
        $this->check($initial && $initial->purpose === 'initial' && (int) $initial->stall_assignment_id === (int) $assignment->id,
            'assessment', 'Renew this assignment to use an approved category assessment.');
        if ($initial->isFixedDays()) {
            $this->fixedPeriod($assignment, $period);
            return $this->fromAssessment($initial, $period) + [
                'computation_mode'=>'automatic', 'rental_override_id'=>null, 'override'=>null,
            ] + $initial->only(self::ASSESSMENT_COLUMNS) + $initial->basis;
        }
        $this->guardReferenceMonth($assignment, $period);
        $automatic = $this->fromAssessment($initial, $period);
        $day = $period.'-01';
        $query = RentalOverride::where('stall_assignment_id', $assignment->id)->covering($day, $day);
        $matches = (DB::transactionLevel() ? $query->lockForUpdate() : $query)->limit(2)->get();
        $this->check($matches->count() < 2, 'rental_override_id', 'More than one approved rental applies to this period.');
        $data = $automatic + [
            'automatic_rate_per_sqm' => $initial->rental_rate_sqm, 'automatic_daily_rental' => $initial->daily_rental,
            'rental_difference' => '0.00', 'rental_override_id' => null, 'override' => null, 'override_type' => null,
            'equivalent_rate_per_sqm' => null, 'contract_rate_consistent' => true, 'computation_mode' => 'automatic',
            'market_section_name' => $initial->market_section_name ?? ($initial->basis['stall_section_name'] ?? null),
        ] + $initial->only(self::ASSESSMENT_COLUMNS) + $initial->basis;
        if ($override = $matches->first()) {
            $this->check((int) $override->initial_assessment_id === (int) $initial->id, 'assessment', 'The approved override belongs to another assessment.');
            $metadata = $override->only(['id', 'revision', 'source_override_id', 'initial_assessment_id', 'override_type',
                'override_rate_per_sqm', 'override_daily_amount', 'reason', 'reference_document', 'remarks', 'requested_by',
                'approved_by', 'effective_from', 'effective_until', 'rental_policy_id', 'authority_snapshot', 'approval_fingerprint']);
            $metadata['approved_at'] = $override->approved_at->toDateTimeString();
            $metadata['status'] = $override->displayStatus($day);
            $metadata['revocation_effective_from'] = $override->revocation_effective_from;
            $amounts = $this->proposedAmounts($initial, $period, $override->override_type,
                $override->override_rate_per_sqm, $override->override_daily_amount);
            $data = array_replace($data, $amounts, [
                'rental_override_id' => $override->id, 'override' => $metadata, 'computation_mode' => 'manual_override',
            ]);
        }
        return $data;
    }

    public function fixedPeriod(StallAssignment $assignment, string $period): array
    {
        Validator::make(['period'=>$period], ['period'=>'required|date_format:Y-m-d'])->validate();
        $initial = optional($assignment->agreement()->first())->assessment;
        $this->check($initial && $initial->isFixedDays(), 'period', 'Choose a billing month for this monthly agreement.');
        $days = (int) $initial->basis['fixed_billing_days'];
        $offset = Carbon::parse($assignment->start_date)->diffInDays(Carbon::parse($period), false);
        $this->check($days >= 1 && $days <= 366 && $offset >= 0 && $offset % $days === 0,
            'period', 'Choose a fixed-day interval starting on the agreement date or an exact interval after it.');
        $end = Carbon::parse($period)->addDays($days - 1)->toDateString();
        $this->check(!$assignment->end_date || $end <= $assignment->end_date,
            'period', 'The complete fixed-day interval must fit within the assignment term. Partial intervals are not enabled.');
        return ['start'=>$period, 'end'=>$end, 'days'=>$days];
    }

    public function guardReferenceMonth(StallAssignment $assignment, string $period): void
    {
        Validator::make(['period' => $period], ['period' => 'required|date_format:Y-m'])->validate();
        $this->check($period >= substr($assignment->start_date, 0, 7) && (!$assignment->end_date || $period <= substr($assignment->end_date, 0, 7)),
            'period', 'The reference month must overlap the assignment term.');
    }

    public function proposedAmounts(RentalAssessment $initial, string $period, string $type, $rate, $direct): array
    {
        $this->check(!$initial->isFixedDays(), 'assessment', 'Monthly rental overrides are unavailable for fixed-day agreements.');
        $days = $this->billingDays($initial->basis, $period);
        $area = $initial->floor_area;
        $this->check(in_array($type, ['daily_amount', 'rate_per_sqm'], true), 'override_type', 'Choose an override method.');
        $isDirect = $type === 'daily_amount';
        $this->check($isDirect ? $rate === null : $direct === null, 'override_type', 'Supply only the amount for the selected method.');
        $amount = $this->decimal($isDirect ? $direct : $rate, $isDirect ? 'override_daily_amount' : 'override_rate_per_sqm', '999999999999.99');
        $daily = $isDirect ? $amount : $this->roundedProduct($area, $amount);
        $printRate = $isDirect ? bcadd(bcdiv($amount, $area, 3), '0.005', 2) : $amount;
        $this->check(bccomp($printRate, '999999999999.99', 2) <= 0, 'equivalent_rate_per_sqm', 'Equivalent rate exceeds the supported limit.');
        $min = $isDirect ? $this->roundedProduct($area, $initial->minimum_rate) : $initial->minimum_rate;
        $max = $isDirect ? $this->roundedProduct($area, $initial->maximum_rate) : $initial->maximum_rate;
        return $this->compose($area, $printRate, $initial->daily_cusa, $days, $daily) + [
            'automatic_rate_per_sqm' => $initial->rental_rate_sqm, 'automatic_daily_rental' => $initial->daily_rental,
            'rental_difference' => bcsub($daily, $initial->daily_rental, 2), 'override_type' => $type,
            'out_of_range' => bccomp($amount, $min, 2) < 0 || bccomp($amount, $max, 2) > 0,
            'zero_rent' => bccomp($daily, '0', 2) === 0, 'equivalent_rate_per_sqm' => $isDirect ? $printRate : null,
            'contract_rate_consistent' => !$isDirect || $this->roundedProduct($area, $printRate) === $daily,
        ];
    }

    public function assess(User $actor, StallAssignment $assignment, $period, $purpose = "initial", array $terms = []): RentalAssessment
    {
        $fixed = $purpose === 'initial' ? (bool) config('billing.fixed_day_enabled', false)
            : (bool) optional(optional($assignment->agreement()->first())->assessment)->isFixedDays();
        $permissions = ["initial" => "assignments.manage", "billing" => "billing.generate", "contract" => "contracts.create"];
        Validator::make(["purpose" => $purpose, "period" => $period], [
            "purpose" => "required|in:initial,billing,contract", "period" => 'required|date_format:'.($fixed ? 'Y-m-d' : 'Y-m'),
        ])->validate();
        if (!$actor->active || !$actor->can($permissions[$purpose]) ||
            ($purpose === "billing" && $period < today()->format($fixed ? 'Y-m-d' : 'Y-m') && !$actor->can("financial.backdate"))) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        $this->check(!$fixed || $purpose !== 'contract', 'contract', 'The monthly contract template cannot be used for fixed-day agreements.');
        return DB::transaction(function () use ($actor, $assignment, $period, $purpose, $terms, $fixed) {
            $tenant = Tenant::whereKey($assignment->tenant_id)->lockForUpdate()->firstOrFail();
            $stall = $purpose === "initial"
                ? Stall::whereKey($assignment->stall_id)->lockForUpdate()->firstOrFail()
                : null;
            $assignment = StallAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $existing = RentalAssessment::where("stall_assignment_id", $assignment->id)
                ->where("period", $period)->where("purpose", $purpose)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $first = $fixed ? $period : $period . "-01";
            $last = $fixed && $purpose !== 'initial' ? $this->fixedPeriod($assignment, $period)['end']
                : Carbon::parse($first)->endOfMonth()->toDateString();
            $agreement = $assignment->agreement()->lockForUpdate()->first();
            if ($purpose === "initial") {
                $this->check(!$agreement && $assignment->status === "active" && ($fixed ? $assignment->start_date : substr($assignment->start_date, 0, 7)) === $period,
                    "assessment", "An initial assessment requires a new active assignment in its commencement month.");
                $business = $assignment->business()->lockForUpdate()->first();
                $this->check($business && (int) $business->tenant_id === (int) $tenant->id,
                    "business_id", "Select an active business belonging to the tenant.");
                $business->setRelation("tenant", $tenant);
                $resolved = $this->resolve($business, $stall, $assignment->start_date, $period, $terms);
                $values = Arr::only($resolved, self::ASSESSMENT_COLUMNS);
                $values["effective_from"] = $assignment->start_date;
                $values["effective_until"] = $assignment->end_date;
                $values["basis"] = Arr::except($resolved, self::ASSESSMENT_COLUMNS);
                $values["snapshot"] = [
                    "tenant" => app(TenantDataService::class)->contractData($tenant, $assignment) + [
                        "first_name" => $tenant->first_name, "last_name" => $tenant->last_name,
                    ],
                    "business" => $business->only([
                        "name", "line_of_business", "permitted_goods", "permit_reference", "registration_reference",
                    ]),
                ];
            } else {
                $initial = optional($agreement)->assessment;
                $this->check((bool) $initial, "assessment", "Renew this assignment to use an approved category assessment.");
                $this->check($assignment->start_date <= $last && (!$assignment->end_date || $assignment->end_date >= $first),
                    "period", "The reference month must overlap the assignment term.");
                if ($purpose === "billing") {
                    $this->check($period <= today()->format($fixed ? 'Y-m-d' : 'Y-m') && $assignment->start_date <= $first &&
                        (!$assignment->end_date || $assignment->end_date >= $last),
                        "period", "An assessment for a bill requires an eligible full month.");
                }
                $resolved = $this->forAssignment($assignment, $period);
                $peer = RentalAssessment::where('stall_assignment_id', $assignment->id)->where('period', $period)
                    ->where('purpose', $purpose === 'billing' ? 'contract' : 'billing')->lockForUpdate()->first();
                if ($peer) {
                    $this->check($this->financialBasis($peer->only(self::ASSESSMENT_COLUMNS) + $peer->basis) === $this->financialBasis($resolved),
                        'assessment', 'A saved assessment for this month has a different financial basis. Review the existing obligations.');
                }
                $values = Arr::only($resolved, self::ASSESSMENT_COLUMNS);
                $values["effective_from"] = $initial->effective_from;
                $values["effective_until"] = $initial->effective_until;
                $values["basis"] = Arr::except($resolved, self::ASSESSMENT_COLUMNS);
                $values["snapshot"] = $initial->snapshot;
            }
            $assessment = RentalAssessment::create($values + [
                "stall_assignment_id" => $assignment->id, "period" => $period, "purpose" => $purpose,
                "created_by" => $actor->id,
            ]);
            if ($assessment->rental_override_id) {
                Audit::record($actor, 'assessment.override_applied', $assessment, [], $assessment->getAttributes());
            }
            return $assessment;
        }, 3);
    }

    private function financialBasis(array $data): array
    {
        // Legacy automatic snapshots predate the explicit comparison columns. Their own saved amounts are authoritative.
        if ($data['computation_mode'] === 'automatic') {
            $data['automatic_rate_per_sqm'] = $data['automatic_rate_per_sqm'] ?? $data['rental_rate_sqm'];
            $data['automatic_daily_rental'] = $data['automatic_daily_rental'] ?? $data['daily_rental'];
        }
        $basis = Arr::except(Arr::only($data, self::ASSESSMENT_COLUMNS), [
            'market_name', 'stall_code', 'business_category_name', 'market_section_id', 'market_section_name',
        ]) + Arr::only($data, ['billing_day_rule', 'fixed_billing_days', 'advance_deposit_authorized',
            'policy_reference', 'rate_reference', 'policy_effective_from', 'policy_effective_until',
            'rate_effective_from', 'rate_effective_until', 'billing_cycle', 'cusa_chargeable']);
        $basis['approval_fingerprint'] = $data['override']['approval_fingerprint'] ?? null;
        ksort($basis);
        return $basis;
    }

    private function check($ok, $field, $message): void
    {
        if (!$ok) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    public function calculate($area, $ratePerSqm, $dailyCusa, $billingDays, bool $fixedDays = false)
    {
        $area = $this->decimal($area, "floor_area", "99999999.99", true);
        $ratePerSqm = $this->decimal($ratePerSqm, "rental_rate_sqm", "999999999999.99", true);
        $dailyCusa = $this->decimal($dailyCusa, "daily_cusa", "999999999999.99");
        if ((!is_int($billingDays) && !is_string($billingDays)) ||
            !preg_match('/^[0-9]{1,3}$/D', (string) $billingDays) ||
            (int) $billingDays < 1 || (int) $billingDays > 366) {
            throw ValidationException::withMessages(["billing_days" => "Billing days must be an integer from 1 to 366."]);
        }

        // Round the displayed daily rent once, so each monthly line reconciles to daily rent times days.
        return $this->compose($area, $ratePerSqm, $dailyCusa, $billingDays, $this->roundedProduct($area, $ratePerSqm), $fixedDays);
    }

    private function roundedProduct($area, $rate): string
    {
        return bcadd(bcmul($area, $rate, 4), '0.005', 2);
    }

    private function compose($area, $ratePerSqm, $dailyCusa, $billingDays, $dailyRental, bool $fixedDays = false): array
    {
        $monthlyRental = bcmul($dailyRental, (string) $billingDays, 2);
        $monthlyCusa = bcmul($dailyCusa, (string) $billingDays, 2);
        // Monthly advance/deposit terms do not establish charges for a fixed-day agreement.
        $advanceRental = $fixedDays ? '0.00' : bcmul($monthlyRental, "2", 2);
        $amounts = [
            "daily_rental" => $dailyRental,
            "monthly_rental" => $monthlyRental,
            "monthly_cusa" => $monthlyCusa,
            "total_monthly_rental_cusa" => $fixedDays ? $monthlyRental : bcadd($monthlyRental, $monthlyCusa, 2),
            "advance_rental" => $advanceRental,
            "security_deposit" => $fixedDays ? '0.00' : $monthlyRental,
            "total_initial_payment" => $fixedDays ? '0.00' : bcadd($advanceRental, $monthlyRental, 2),
        ];
        foreach ($amounts as $field => $amount) {
            if (bccomp($amount, "999999999999.99", 2) > 0) {
                throw ValidationException::withMessages([$field => "Calculated amount exceeds the supported monetary limit."]);
            }
        }
        return array_merge([
            "floor_area" => $area,
            "rental_rate_sqm" => $ratePerSqm,
            "daily_cusa" => $dailyCusa,
            "billing_days" => (int) $billingDays,
        ], $amounts);
    }

    private function decimal($value, $field, $maximum, $positive = false)
    {
        if ((!is_string($value) && !is_int($value)) ||
            !preg_match('/^[0-9]{1,12}(\.[0-9]{1,2})?$/D', (string) $value) ||
            bccomp((string) $value, $maximum, 2) > 0 ||
            ($positive && bccomp((string) $value, "0", 2) <= 0)) {
            throw ValidationException::withMessages([$field => "Use a valid " . ($positive ? "positive" : "nonnegative") . " decimal with at most two decimal places within the supported limit."]);
        }
        return Money::normalize($value);
    }
}
