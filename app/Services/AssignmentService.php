<?php
namespace App\Services;
use App\Models\{User, Stall, Tenant, Business, Market, StallAssignment, RentalAgreement, RentalPolicy};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AssignmentService
{
    public function assign(User $actor, array $data)
    {
        $this->authorize($actor);
        if (isset($data['agreement_reference']) && is_string($data['agreement_reference'])
            && trim($data['agreement_reference']) === '') {
            $data['agreement_reference'] = null;
        }
        $rules = [
            "stall_id" => "required|integer|exists:stalls,id",
            "tenant_id" => "required|integer|exists:tenants,id",
            "business_id" => "required|integer|exists:businesses,id",
            "rental_rate_id" => "prohibited",
            "start_date" => "required|date_format:Y-m-d|before_or_equal:today",
            "end_date" => "nullable|date_format:Y-m-d|after_or_equal:start_date",
            "agreement_reference" => "nullable|string|max:120|unique:rental_agreements,reference",
            "remarks" => "nullable|string|max:2000",
            "concurrent_authorization_reference" => "nullable|string|max:120",
            "concurrent_authorization_reason" => "nullable|required_with:concurrent_authorization_reference|string|min:5|max:1000",
            "helpers" => "nullable|array|max:2",
        ];
        if (config('billing.fixed_day_enabled', false)) {
            $rules += ['billing_interval_days'=>'nullable|integer|between:1,366',
                'daily_cusa'=>['nullable','regex:/\A[0-9]{1,12}(\.[0-9]{1,2})?\z/']];
        }
        $d = Validator::make($data, $rules)->validate();
        return DB::transaction(function () use ($actor, $d) {
            $tenant = Tenant::whereKey($d["tenant_id"])->lockForUpdate()->firstOrFail();
            $stall = Stall::whereKey($d["stall_id"])->lockForUpdate()->firstOrFail();
            $this->check(
                $tenant->status === "active" && in_array($stall->status, ["vacant", "reserved"]),
                "Only active tenants and vacant/reserved stalls may be assigned.",
            );
            $this->check($stall->market->status === 'active' && $stall->section
                && (int) $stall->section->market_id === (int) $stall->market_id && $stall->section->status === 'active',
                'Market and section must be active.');
            $business = Business::whereKey($d["business_id"])->lockForUpdate()->firstOrFail();
            $this->check((int) $business->tenant_id === (int) $tenant->id && $business->status === "active",
                "Business does not belong to this tenant or is inactive.");
            $end = $d["end_date"] ?? "9999-12-31";
            $conflict = $this->overlapping($stall->assignments(), $d["start_date"], $end)->lockForUpdate()->exists();
            $this->check(!$conflict, "Assignment dates overlap an existing occupancy.");
            $d['concurrency_policy'] = $this->authorizeConcurrency($tenant, $stall, $d, $end);
            $assignment = StallAssignment::create([
                "stall_id" => $stall->id,
                "tenant_id" => $tenant->id,
                "business_id" => $business->id,
                "active_stall_id" => $stall->id,
                "start_date" => $d["start_date"],
                "end_date" => $d["end_date"] ?? null,
                "status" => "active",
                "remarks" => $d["remarks"] ?? null,
                "authorized_by" => $actor->id,
                "concurrent_authorization_reference" => $d["concurrent_authorization_reference"] ?? null,
                "concurrent_authorization_reason" => $d["concurrent_authorization_reason"] ?? null,
            ]);
            if (!empty($d["helpers"])) {
                app(TenantDataService::class)->saveHelpers($actor, $assignment, $d["helpers"]);
            }
            $assessment = app(RentalCalculationService::class)
                ->assess($actor, $assignment, config('billing.fixed_day_enabled', false)
                    ? $assignment->start_date : substr($assignment->start_date, 0, 7), 'initial', $d);
            RentalAgreement::create([
                "stall_assignment_id" => $assignment->id,
                "rental_assessment_id" => $assessment->id,
                "reference" => $d["agreement_reference"] ?? 'RA-'.Str::uuid(),
                "monthly_amount" => $assessment->total_monthly_rental_cusa,
                "start_date" => $d["start_date"],
                "end_date" => $d["end_date"] ?? null,
            ]);
            $stall->update(["status" => "occupied", "updated_by" => $actor->id]);
            Audit::record(
                $actor,
                "assignment.created",
                $assignment,
                [],
                $assignment->getAttributes(),
            );
            return $assignment;
        }, 3);
    }

    private function overlapping($query, $start, $end)
    {
        return $query->where("start_date", "<=", $end)->where(function ($query) use ($start) {
            $query->whereNull("end_date")->orWhere("end_date", ">=", $start);
        });
    }

    private function authorizeConcurrency(Tenant $tenant, Stall $stall, array $data, $end): ?RentalPolicy
    {
        // The tenant lock serializes concurrency checks. Acquire involved market locks in ascending ID order.
        $other = $this->overlapping(
            StallAssignment::where("tenant_id", $tenant->id)->where("status", "active"),
            $data["start_date"],
            $end,
        )->lockForUpdate()->get(["id", "stall_id"]);
        $markets = $other->load("stall:id,market_id")->pluck("stall.market_id")->push($stall->market_id)->unique();
        $lockedMarkets = Market::whereIn('id', $markets)->orderBy('id')->lockForUpdate()->get(['id', 'status']);
        $section = $stall->section()->where('market_id', $stall->market_id)->lockForUpdate()->first();
        $this->check(
            $lockedMarkets->firstWhere('id', $stall->market_id)->status === 'active' && $section && $section->status === 'active',
            'Market and section must be active.',
        );
        if ($other->isEmpty()) {
            return null;
        }
        $this->check(mb_strlen(trim($data["concurrent_authorization_reason"] ?? "")) >= 5,
            "Supply a reason authorizing the concurrent assignments.");
        $calculation = app(RentalCalculationService::class);
        $assignmentPolicy = null;
        foreach ($markets->sort() as $marketId) {
            $policy = $calculation->marketPolicy($marketId, $data["start_date"]);
            $this->check($policy->max_concurrent_assignments >= $other->count() + 1,
                "Every involved market's approved policy must permit the tenant's total concurrent assignments.");
            if ((int) $marketId === (int) $stall->market_id) {
                $assignmentPolicy = $policy;
            }
        }
        return $assignmentPolicy;
    }
    public function end(User $actor, StallAssignment $assignment, $date, $reason)
    {
        $this->authorize($actor);
        Validator::make(
            ["end_date" => $date, "reason" => $reason],
            [
                "end_date" => "required|date_format:Y-m-d|before_or_equal:today",
                "reason" => "required|string|min:5|max:1000",
            ],
        )->validate();
        return DB::transaction(function () use ($actor, $assignment, $date, $reason) {
            Tenant::whereKey($assignment->tenant_id)->lockForUpdate()->firstOrFail();
            $stall = Stall::whereKey($assignment->stall_id)->lockForUpdate()->firstOrFail();
            $a = StallAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $this->check(
                $a->status === "active" &&
                    $date >= $a->start_date &&
                    (!$a->end_date || $date <= $a->end_date),
                "Assignment is ended or end date is outside its term.",
            );
            $fixed = optional(optional($a->agreement)->assessment)->isFixedDays();
            $this->check($fixed ? !$a->bills()->where('coverage_end', '>', $date)->exists()
                : !$a->bills()->where("period", ">", substr($date, 0, 7))->exists(),
                "Later bills exist. Resolve billing before ending this assignment.");
            if (!$fixed && $date < \Carbon\Carbon::parse($date)->endOfMonth()->toDateString()) {
                $this->check(
                    !$a->bills()->where("period", substr($date, 0, 7))->exists(),
                    "A bill covers the full month. End at the month boundary; partial-month corrections require approved policy.",
                );
            }
            $old = $a->getAttributes();
            $a->update(["end_date" => $date, "status" => "ended", "active_stall_id" => null]);
            optional($a->agreement)->update(["end_date" => $date]);
            $stall->update(["status" => "vacant", "updated_by" => $actor->id]);
            Audit::record($actor, "assignment.ended", $a, $old, $a->getAttributes(), $reason);
            return $a;
        }, 3);
    }
    public function transfer(User $actor, StallAssignment $assignment, array $data)
    {
        $this->authorize($actor);
        Validator::make($data, [
            "stall_id" => "required|integer|exists:stalls,id",
            "business_id" => "required|integer|exists:businesses,id",
            "rental_rate_id" => "prohibited",
            "start_date" => "required|date_format:Y-m-d|before_or_equal:today",
            "reason" => "required|string|min:5|max:1000",
        ])->validate();
        $initial = optional($assignment->agreement)->assessment;
        if ($initial && $initial->isFixedDays()) {
            $days = (int) $initial->basis['fixed_billing_days'];
            $offset = \Carbon\Carbon::parse($assignment->start_date)->diffInDays(\Carbon\Carbon::parse($data['start_date']), false);
            $this->check($days > 0 && $offset > 0 && $offset % $days === 0,
                'Renew or transfer on a fixed-day interval boundary.');
            if (!config('billing.fixed_day_enabled', false)) {
                $this->check(substr($data['start_date'], 8) === '01',
                    'The new monthly agreement must also start on the first day of a month.');
            }
        } else {
            $this->check(substr($data["start_date"], 8) === "01",
                "Transfers start on a month boundary until proration policy is approved.");
        }
        $this->check(
            $data["start_date"] > $assignment->start_date,
            "Transfer must follow the original start date.",
        );
        return DB::transaction(function () use ($actor, $assignment, $data) {
            Tenant::whereKey($assignment->tenant_id)->lockForUpdate()->firstOrFail();
            Stall::whereIn("id", [$assignment->stall_id, $data["stall_id"]])
                ->orderBy("id")
                ->lockForUpdate()
                ->get();
            $this->end(
                $actor,
                $assignment,
                \Carbon\Carbon::parse($data["start_date"])->subDay()->toDateString(),
                $data["reason"],
            );
            $data["tenant_id"] = $assignment->tenant_id;
            $new = $this->assign($actor, $data);
            Audit::record(
                $actor,
                "assignment.transferred",
                $new,
                [],
                ["previous_assignment_id" => $assignment->id],
                $data["reason"],
            );
            return $new;
        }, 3);
    }
    private function authorize($actor)
    {
        if (!$actor->active || !$actor->can("assignments.manage")) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }
    }
    private function check($condition, $message)
    {
        if (!$condition) {
            throw ValidationException::withMessages(["assignment" => $message]);
        }
    }
}
