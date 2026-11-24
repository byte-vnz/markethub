<?php
namespace App\Http\Controllers;
use App\Models\{Payment, Tenant};
use App\Services\{PaymentService, Ledger};
use App\Http\Requests\PaymentRequest;
use Illuminate\Http\Request;
class PaymentController extends Controller
{
    public function index(Request $r)
    {
        $this->authorize("payments.view");
        $q = Payment::with("tenant", "officer", "reversal", "allocations.bill.stall");
        foreach (["tenant_id", "status", "recorded_by"] as $key) {
            if ($r->filled($key)) {
                $q->where($key, $r->$key);
            }
        }
        foreach (["market_id", "section_id", "stall_id"] as $key) {
            if ($r->filled($key)) {
                $q->whereHas("allocations.bill.stall", function ($q) use ($r, $key) {
                    $q->where(
                        [
                            "market_id" => "market_id",
                            "section_id" => "market_section_id",
                            "stall_id" => "id",
                        ][$key],
                        $r->$key,
                    );
                });
            }
        }
        if ($r->filled("from")) {
            $q->where("payment_date", ">=", $r->from);
        }
        if ($r->filled("to")) {
            $q->where("payment_date", "<=", $r->to);
        }
        return view("payments.index", [
            "payments" => $q->latest()->paginate(20)->withQueryString(),
        ]);
    }
    public function create(Request $r)
    {
        $this->authorize("payments.record");
        $replacement = $r->replacement
            ? Payment::with("reversal")->findOrFail($r->replacement)
            : null;
        if ($replacement) {
            abort_unless($replacement->reversal || $replacement->status === "void", 422);
        }
        $tenantId = $r->tenant_id ?: ($replacement ? $replacement->tenant_id : null);
        $tenant = $tenantId ? Tenant::findOrFail($tenantId) : null;
        $bills = $tenant
            ? Ledger::bills()
                ->where("tenant_id", $tenant->id)
                ->whereRaw(Ledger::balanceSql() . ">0")
                ->with("stall")
                ->get()
            : collect();
        return view("payments.create", [
            "replacement" => $replacement,
            "tenant" => $tenant,
            "tenants" => Tenant::orderBy("name")->get(),
            "bills" => $bills,
        ]);
    }
    public function store(PaymentRequest $r, PaymentService $s)
    {
        $p = $s->createDraft($r->user(), $r->validated());
        return redirect()
            ->route("payments.show", $p)
            ->with("success", "Payment recorded as draft. Review before posting.");
    }
    public function show(Payment $payment)
    {
        $this->authorize("view", $payment);
        return view("payments.show", [
            "payment" => $payment->load("tenant", "officer", "reversal", "allocations.bill.stall"),
        ]);
    }
    public function post(Request $r, Payment $payment, PaymentService $s)
    {
        $s->post($r->user(), $payment);
        return back()->with("success", "Payment posted; pending Treasury reconciliation.");
    }
    public function reverse(Request $r, Payment $payment, PaymentService $s)
    {
        $s->reverse($r->user(), $payment, $r->reason);
        return back()->with("success", "Reversal recorded. Original payment preserved.");
    }
    public function reconcile(Request $r, Payment $payment, PaymentService $s)
    {
        $s->reconcile($r->user(), $payment, $r->reason);
        return back()->with("success", "Treasury confirmation recorded.");
    }
    public function cancel(Request $r, Payment $payment, PaymentService $s)
    {
        $s->cancelDraft($r->user(), $payment, $r->reason);
        return back()->with(
            "success",
            "Draft voided and retained. Record a linked correction if needed.",
        );
    }
}
