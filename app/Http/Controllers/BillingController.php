<?php
namespace App\Http\Controllers;
use App\Models\{RentalBill, StallAssignment};
use App\Services\{BillingService, Ledger};
use Illuminate\Http\Request;
class BillingController extends Controller
{
    public function index(Request $r)
    {
        $this->authorize("billing.view");
        $q = RentalBill::with("tenant", "stall");
        if ($r->filled("tenant_id")) {
            $q->where("tenant_id", $r->tenant_id);
        }
        if ($r->filled("period")) {
            $q->where("period", $r->period);
        }
        if ($r->filled("status")) {
            $q->where("status", $r->status);
        }
        return view("billing.index", ["bills" => $q->latest()->paginate(20)->withQueryString()]);
    }
    public function create()
    {
        $this->authorize("billing.generate");
        return view("billing.create", [
            "assignments" => StallAssignment::with("stall", "tenant", "agreement")
                ->where("status", "active")
                ->get(),
        ]);
    }
    public function generate(Request $r, BillingService $s)
    {
        $this->authorize("billing.generate");
        $d = $r->validate([
            "assignment_ids" => "required|array|min:1|max:500",
            "assignment_ids.*" => "required|integer|distinct|exists:stall_assignments,id",
            "period" => "required|date_format:Y-m",
            "due_date" => "required|date_format:Y-m-d",
        ]);
        $count = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($r, $s, $d, &$count) {
            foreach (
                StallAssignment::whereIn("id", $d["assignment_ids"])
                    ->orderBy("tenant_id")
                    ->orderBy("id")
                    ->get()
                as $a
            ) {
                $s->generate($r->user(), $a, $d["period"], $d["due_date"]);
                $count++;
            }
        });
        return redirect()
            ->route("billing.index", ["period" => $d["period"]])
            ->with("success", $count . " draft bill(s) ready for review. Nothing was posted.");
    }
    public function show(RentalBill $bill)
    {
        $this->authorize("view", $bill);
        return view("billing.show", [
            "bill" => $bill->load("tenant", "stall", "assessment", "items", "adjustments", "allocations.payment"),
        ]);
    }
    public function post(Request $r, RentalBill $bill, BillingService $s)
    {
        $s->post($r->user(), $bill);
        return back()->with("success", "Bill posted.");
    }
    public function adjust(Request $r, RentalBill $bill, BillingService $s)
    {
        $s->adjust(
            $r->user(),
            $bill,
            $r->only(["amount", "reason", "approval_reference", "idempotency_key"]),
        );
        return back()->with("success", "Adjustment appended to the bill.");
    }
    public function receivables(Request $r)
    {
        $this->authorize("billing.view");
        $q = Ledger::bills()
            ->with("tenant", "stall")
            ->whereRaw(Ledger::balanceSql() . ">0");
        if ($r->filled("tenant_id")) {
            $q->where("tenant_id", $r->tenant_id);
        }
        if ($r->boolean("overdue")) {
            $q->where("due_date", "<", today()->toDateString());
        }
        return view("billing.receivables", [
            "bills" => $q->orderBy("due_date")->paginate(20)->withQueryString(),
            "total" => Ledger::sum($q, Ledger::balanceSql()),
        ]);
    }
}
