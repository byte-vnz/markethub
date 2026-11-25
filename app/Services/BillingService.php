<?php
namespace App\Services;
use App\Models\{User, Tenant, StallAssignment, RentalBill, BillingAdjustment};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
class BillingService
{
    public function generate(User $actor, StallAssignment $assignment, $period, $due)
    {
        $this->authorize($actor, "billing.generate");
        $fixed = (bool) optional(optional($assignment->agreement()->first())->assessment)->isFixedDays();
        Validator::make(
            ["period" => $period, "due_date" => $due],
            ["period" => 'required|date_format:'.($fixed ? 'Y-m-d' : 'Y-m'), "due_date" => "required|date_format:Y-m-d"],
        )->validate();
        $start = Carbon::createFromFormat("Y-m-d", $fixed ? $period : $period . "-01")->startOfDay();
        $end = $start->copy()->endOfMonth()->toDateString();
        $this->check($period <= today()->format($fixed ? 'Y-m-d' : 'Y-m'), "Future billing is not enabled.");
        if ($period < today()->format($fixed ? 'Y-m-d' : 'Y-m')) {
            $this->authorize($actor, "financial.backdate");
        }
        $this->check($due >= $start->toDateString(), "Due date cannot precede billing period.");
        return DB::transaction(function () use ($actor, $assignment, $period, $due, $end, $fixed) {
            Tenant::whereKey($assignment->tenant_id)->lockForUpdate()->firstOrFail();
            $a = StallAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $existing = RentalBill::where("stall_assignment_id", $a->id)
                ->where("period", $period)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }
            if ($fixed) {
                $range = app(RentalCalculationService::class)->fixedPeriod($a, $period);
                $end = $range['end'];
                $overlap = $a->bills()->where(function ($query) use ($period, $end) {
                    $query->where(function ($query) use ($period, $end) {
                        $query->where('coverage_start', '<=', $end)->where('coverage_end', '>=', $period);
                    })->orWhere(function ($query) use ($period, $end) {
                        $query->whereNull('coverage_start')->whereBetween('period', [substr($period, 0, 7), substr($end, 0, 7)]);
                    });
                })->lockForUpdate()->exists();
                $this->check(!$overlap, 'A saved bill already covers part of this interval.');
            } else {
                $this->check($a->start_date <= $period . "-01" && (!$a->end_date || $a->end_date >= $end),
                    "Full-month agreement required. Partial-month billing awaits approved proration policy.");
            }
            $agreement = $a->agreement()->lockForUpdate()->first();
            $this->check((bool) $agreement, "A rental agreement is required.");
            $assessment = $agreement->rental_assessment_id
                ? app(RentalCalculationService::class)->assess($actor, $a, $period, "billing")
                : null;
            $bill = RentalBill::create([
                "reference" => ($fixed ? 'W-' : 'B-') . str_replace("-", "", $period) . "-" . $a->id,
                "stall_assignment_id" => $a->id,
                "tenant_id" => $a->tenant_id,
                "stall_id" => $a->stall_id,
                "period" => $period,
                "due_date" => $due,
                "rental_assessment_id" => optional($assessment)->id,
                "amount" => $assessment ? $assessment->total_monthly_rental_cusa : Money::positive($agreement->monthly_amount),
                "status" => "draft",
                "created_by" => $actor->id,
            ] + ($fixed ? ['coverage_start'=>$period, 'coverage_end'=>$end] : []));
            if ($assessment) {
                $items = [
                    ["description" => "Base rent / " . $agreement->reference, "amount" => $assessment->monthly_rental],
                ];
                if (!$assessment->isFixedDays()) {
                    $items[] = ["description" => "CUSA / " . $agreement->reference, "amount" => $assessment->monthly_cusa];
                }
                $bill->items()->createMany($items);
            } else {
                $bill->items()->create([
                    "description" => "Monthly rent / " . $agreement->reference,
                    "amount" => $bill->amount,
                ]);
            }
            Audit::record($actor, "bill.generated", $bill, [], $bill->getAttributes());
            return $bill;
        }, 3);
    }
    public function post(User $actor, RentalBill $bill)
    {
        $this->authorize($actor, "billing.post");
        return DB::transaction(function () use ($actor, $bill) {
            Tenant::whereKey($bill->tenant_id)->lockForUpdate()->firstOrFail();
            $b = RentalBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if ($b->status === "posted") {
                return $b;
            }
            $this->check($b->status === "draft", "Only drafts can be posted.");
            $a = StallAssignment::whereKey($b->stall_assignment_id)->lockForUpdate()->firstOrFail();
            $this->check(
                $a->start_date <= ($b->coverage_start ?: $b->period . "-01") &&
                    (!$a->end_date ||
                        $a->end_date >=
                            ($b->coverage_end ?: Carbon::parse($b->period . "-01")
                                ->endOfMonth()
                                ->toDateString())),
                "Assignment dates changed; this bill cannot be posted.",
            );
            $b->update(["status" => "posted", "posted_by" => $actor->id, "posted_at" => now()]);
            Audit::record(
                $actor,
                "bill.posted",
                $b,
                [],
                ["status" => "posted", "amount" => $b->amount],
            );
            return $b;
        }, 3);
    }
    public function adjust(User $actor, RentalBill $bill, array $data)
    {
        $this->authorize($actor, "billing.adjust");
        Validator::make($data, [
            "idempotency_key" => "required|uuid",
            "amount" => "required",
            "reason" => "required|string|min:5|max:1000",
            "approval_reference" => "required|string|max:120",
        ])->validate();
        $amount = Money::normalize($data["amount"], true);
        $this->check(bccomp($amount, "0", 2) !== 0, "Adjustment must be nonzero.");
        return DB::transaction(function () use ($actor, $bill, $data, $amount) {
            Tenant::whereKey($bill->tenant_id)->lockForUpdate()->firstOrFail();
            $b = RentalBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $this->check($b->status === "posted", "Only posted bills can be adjusted.");
            $hash = hash(
                "sha256",
                json_encode([$b->id, $amount, $data["reason"], $data["approval_reference"]]),
            );
            $existing = BillingAdjustment::where(
                "idempotency_key",
                $data["idempotency_key"],
            )->first();
            if ($existing) {
                $this->check(
                    $existing->created_by == $actor->id &&
                        hash_equals($existing->request_hash, $hash),
                    "Adjustment submission key belongs to different information.",
                );
                return $existing;
            }
            $this->check(
                bccomp(bcadd($b->balance(), $amount, 2), "0", 2) >= 0,
                "Adjustment cannot create an advance credit.",
            );
            $a = BillingAdjustment::create([
                "idempotency_key" => $data["idempotency_key"],
                "request_hash" => $hash,
                "rental_bill_id" => $b->id,
                "amount" => $amount,
                "reason" => $data["reason"],
                "approval_reference" => $data["approval_reference"],
                "created_by" => $actor->id,
            ]);
            Audit::record($actor, "bill.adjusted", $a, [], ["amount" => $amount], $data["reason"]);
            return $a;
        }, 3);
    }
    private function authorize($actor, $permission)
    {
        if (!$actor->active || !$actor->can($permission)) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }
    }
    private function check($ok, $message)
    {
        if (!$ok) {
            throw ValidationException::withMessages(["billing" => $message]);
        }
    }
}
