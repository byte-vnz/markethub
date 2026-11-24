<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\{Market, Payment, StallAssignment, AuditLog};
use App\Services\Ledger;
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize("dashboard.view");
        $request->validate([
            "market_id" => "nullable|integer|exists:markets,id",
            "from" => "nullable|date_format:Y-m-d",
            "to" => "nullable|date_format:Y-m-d|after_or_equal:from",
        ]);
        $market = $request->market_id;
        $from = $request->from ?: today()->startOfYear()->toDateString();
        $to = $request->to ?: today()->toDateString();
        $data = Ledger::dashboard($market, $from, $to);
        $payments = collect();
        $assignments = collect();
        $overdue = collect();
        $activity = collect();
        if ($request->user()->can("payments.view")) {
            $q = Payment::with("tenant")
                ->effective()
                ->whereBetween("payment_date", [$from, $to]);
            if ($market) {
                $q->whereHas("allocations.bill.stall", function ($q) use ($market) {
                    $q->where("market_id", $market);
                });
            }
            $payments = $q->latest("posted_at")->limit(5)->get();
        }
        if ($request->user()->can("tenants.view")) {
            $q = StallAssignment::with("tenant", "stall");
            if ($market) {
                $q->whereHas("stall", function ($q) use ($market) {
                    $q->where("market_id", $market);
                });
            }
            $assignments = $q->latest()->limit(5)->get();
            $overdue = Ledger::bills($market)
                ->with("tenant")
                ->where("due_date", "<", today()->toDateString())
                ->whereRaw(Ledger::balanceSql() . ">0")
                ->orderBy("due_date")
                ->limit(5)
                ->get();
        }
        if ($request->user()->can("audit.view")) {
            $activity = AuditLog::with("actor")->latest()->limit(5)->get();
        }
        return view(
            "dashboard",
            compact(
                "data",
                "market",
                "from",
                "to",
                "payments",
                "assignments",
                "overdue",
                "activity",
            ) + ["markets" => Market::orderBy("name")->get()],
        );
    }
}
