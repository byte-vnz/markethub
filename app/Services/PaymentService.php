<?php
namespace App\Services;
use App\Models\{User, Tenant, RentalBill, Payment, PaymentReversal};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;
class PaymentService
{
    public function createDraft(User $actor, array $data)
    {
        $this->authorize($actor, "payments.record");
        $d = Validator::make($data, [
            "replacement_of_id" => "nullable|integer|exists:payments,id",
            "tenant_id" => "required|integer|exists:tenants,id",
            "payment_date" => "required|date_format:Y-m-d|before_or_equal:today",
            "amount" => "required",
            "method" => "required|in:cash,bank_transfer,check",
            "receipt_reference" => "required|string|max:120",
            "idempotency_key" => "required|uuid",
            "allocations" => "required|array|min:1|max:100",
            "allocations.*" => "required",
            "remarks" => "nullable|string|max:2000",
        ])->validate();
        if ($d["payment_date"] !== today()->toDateString()) {
            $this->authorize($actor, "financial.backdate");
        }
        $d["receipt_reference"] = strtoupper(trim($d["receipt_reference"]));
        $d["amount"] = Money::positive($d["amount"]);
        ksort($d["allocations"], SORT_NUMERIC);
        foreach ($d["allocations"] as $id => $amount) {
            $this->check(ctype_digit((string) $id), "Invalid bill identifier.");
            $d["allocations"][$id] = Money::positive($amount);
        }
        $hash = hash("sha256", json_encode($d));
        return DB::transaction(function () use ($actor, $d, $hash) {
            Tenant::whereKey($d["tenant_id"])->lockForUpdate()->firstOrFail();
            $existing = Payment::where("idempotency_key", $d["idempotency_key"])->first();
            if ($existing) {
                $this->check(
                    $existing->recorded_by == $actor->id &&
                        hash_equals($existing->request_hash, $hash),
                    "Submission key was used for different payment information.",
                );
                return $existing;
            }
            $this->check(
                !Payment::where("active_receipt_reference", $d["receipt_reference"])->exists(),
                "This receipt reference is already reserved by an unreversed payment.",
            );
            if (!empty($d["replacement_of_id"])) {
                $original = Payment::whereKey($d["replacement_of_id"])
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->check(
                    !Payment::where("receipt_reference", $d["receipt_reference"])->exists() ||
                        $original->receipt_reference === $d["receipt_reference"],
                    "The receipt history belongs to a different payment. Select its actual correction record.",
                );
                $this->check(
                    ($original->status === "void" ||
                        ($original->status === "posted" && $original->reversal)) &&
                        !Payment::where("replacement_of_id", $original->id)->exists(),
                    "Correction must replace a voided draft or reversed payment that has not already been replaced.",
                );
            } elseif (Payment::where("receipt_reference", $d["receipt_reference"])->exists()) {
                $this->check(
                    false,
                    "Use the reversed payment correction action to reuse its receipt reference.",
                );
            }
            $this->validateAllocations($d["tenant_id"], $d["amount"], $d["allocations"]);
            $p = Payment::create([
                "reference" =>
                    "P-" . strtoupper(substr(str_replace("-", "", $d["idempotency_key"]), 0, 20)),
                "tenant_id" => $d["tenant_id"],
                "payment_date" => $d["payment_date"],
                "amount" => $d["amount"],
                "method" => $d["method"],
                "receipt_reference" => $d["receipt_reference"],
                "active_receipt_reference" => $d["receipt_reference"],
                "replacement_of_id" => $d["replacement_of_id"] ?? null,
                "idempotency_key" => $d["idempotency_key"],
                "request_hash" => $hash,
                "status" => "draft",
                "reconciliation_status" => "pending",
                "recorded_by" => $actor->id,
                "remarks" => $d["remarks"] ?? null,
            ]);
            foreach ($d["allocations"] as $id => $amount) {
                $p->allocations()->create(["rental_bill_id" => $id, "amount" => $amount]);
            }
            Audit::record(
                $actor,
                "payment.recorded",
                $p,
                [],
                ["amount" => $p->amount, "status" => "draft"],
            );
            return $p;
        }, 3);
    }
    public function post(User $actor, Payment $payment)
    {
        $this->authorize($actor, "payments.post");
        return DB::transaction(function () use ($actor, $payment) {
            Tenant::whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->status === "posted") {
                return $p;
            }
            $this->check($p->status === "draft", "Only drafts may be posted.");
            $allocations = $p
                ->allocations()
                ->orderBy("rental_bill_id")
                ->pluck("amount", "rental_bill_id")
                ->map(function ($v) {
                    return (string) $v;
                })
                ->all();
            $this->validateAllocations($p->tenant_id, $p->amount, $allocations);
            $p->update(["status" => "posted", "posted_by" => $actor->id, "posted_at" => now()]);
            Audit::record(
                $actor,
                "payment.posted",
                $p,
                [],
                ["amount" => $p->amount, "status" => "posted"],
            );
            return $p;
        }, 3);
    }

    public function cancelDraft(User $actor, Payment $payment, $reason)
    {
        $this->authorize($actor, "payments.record");
        Validator::make(
            ["reason" => $reason],
            ["reason" => "required|string|min:5|max:1000"],
        )->validate();
        return DB::transaction(function () use ($actor, $payment, $reason) {
            Tenant::whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->recorded_by != $actor->id && !$actor->can("payments.reverse")) {
                throw new \Illuminate\Auth\Access\AuthorizationException();
            }
            if ($p->status === "void") {
                return $p;
            }
            $this->check($p->status === "draft", "Posted payments require a reversal.");
            $p->update(["status" => "void", "active_receipt_reference" => null]);
            Audit::record($actor, "payment.draft_voided", $p, [], ["status" => "void"], $reason);
            return $p;
        }, 3);
    }
    public function reverse(User $actor, Payment $payment, $reason)
    {
        $this->authorize($actor, "payments.reverse");
        Validator::make(
            ["reason" => $reason],
            ["reason" => "required|string|min:5|max:1000"],
        )->validate();
        return DB::transaction(function () use ($actor, $payment, $reason) {
            Tenant::whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->check($p->status === "posted", "Only posted payments may be reversed.");
            if ($p->reversal) {
                return $p->reversal;
            }
            $p->update(["active_receipt_reference" => null]);
            $r = PaymentReversal::create([
                "payment_id" => $p->id,
                "reason" => $reason,
                "reversed_by" => $actor->id,
            ]);
            Audit::record($actor, "payment.reversed", $p, [], [], $reason);
            return $r;
        }, 3);
    }
    public function reconcile(User $actor, Payment $payment, $reason)
    {
        $this->authorize($actor, "payments.reconcile");
        Validator::make(
            ["reason" => $reason],
            ["reason" => "required|string|min:5|max:1000"],
        )->validate();
        return DB::transaction(function () use ($actor, $payment, $reason) {
            Tenant::whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->check(
                $p->status === "posted" && !$p->reversal,
                "Only unreversed posted payments can be confirmed.",
            );
            if ($p->reconciliation_status === "confirmed") {
                return $p;
            }
            $p->update([
                "reconciliation_status" => "confirmed",
                "reconciled_by" => $actor->id,
                "reconciled_at" => now(),
            ]);
            Audit::record(
                $actor,
                "payment.reconciled",
                $p,
                [],
                ["reconciliation_status" => "confirmed"],
                $reason,
            );
            return $p;
        }, 3);
    }
    private function validateAllocations($tenantId, $amount, array $allocations)
    {
        $total = "0.00";
        $ids = array_keys($allocations);
        sort($ids, SORT_NUMERIC);
        $bills = RentalBill::whereIn("id", $ids)
            ->orderBy("id")
            ->lockForUpdate()
            ->get()
            ->keyBy("id");
        foreach ($allocations as $id => $value) {
            $bill = $bills->get($id);
            $this->check(
                $bill && $bill->tenant_id == $tenantId && $bill->status === "posted",
                "All allocations must belong to this tenant and posted bills.",
            );
            $value = Money::positive((string) $value);
            $this->check(
                bccomp($value, $bill->balance(), 2) <= 0,
                "Allocation exceeds the outstanding bill balance.",
            );
            $total = bcadd($total, $value, 2);
        }
        $this->check(
            bccomp($total, $amount, 2) === 0,
            "Allocate the entire payment exactly. Advance credit requires an approved policy.",
        );
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
            throw ValidationException::withMessages(["payment" => $message]);
        }
    }
}
