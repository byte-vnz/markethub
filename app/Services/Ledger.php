<?php
namespace App\Services;
use App\Models\{RentalBill, Payment, Stall, Tenant};
use Illuminate\Support\Facades\DB;
class Ledger
{
    public static function totalSql($alias = "rental_bills")
    {
        return "($alias.amount + COALESCE((SELECT SUM(ba.amount) FROM billing_adjustments ba WHERE ba.rental_bill_id=$alias.id),0))";
    }
    public static function paidSql($alias = "rental_bills")
    {
        return "COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa JOIN payments p ON p.id=pa.payment_id WHERE pa.rental_bill_id=$alias.id AND p.status='posted' AND NOT EXISTS(SELECT 1 FROM payment_reversals pr WHERE pr.payment_id=p.id)),0)";
    }
    public static function balanceSql($alias = "rental_bills")
    {
        return "(" . self::totalSql($alias) . "-" . self::paidSql($alias) . ")";
    }
    public static function bills($market = null)
    {
        $q = RentalBill::query()->where("rental_bills.status", "posted");
        if ($market) {
            $q->whereHas("stall", function ($q) use ($market) {
                $q->where("market_id", $market);
            });
        }
        return $q;
    }
    public static function sum($q, $sql)
    {
        return bcadd(
            (string) (clone $q)
                ->selectRaw("COALESCE(SUM(" . $sql . "),0) AS aggregate")
                ->value("aggregate"),
            "0",
            2,
        );
    }
    public static function dashboard($market, $from, $to)
    {
        $stalls = Stall::query();
        if ($market) {
            $stalls->where("market_id", $market);
        }
        $counts = (clone $stalls)
            ->selectRaw("status, COUNT(*) AS count")
            ->groupBy("status")
            ->pluck("count", "status")
            ->all();
        $total = array_sum($counts);
        $bills = self::bills($market);
        $range = (clone $bills)->whereBetween("period", [substr($from, 0, 7), substr($to, 0, 7)]);
        $collections = DB::table("payment_allocations AS pa")
            ->join("payments AS p", "p.id", "=", "pa.payment_id")
            ->join("rental_bills AS b", "b.id", "=", "pa.rental_bill_id")
            ->join("stalls AS s", "s.id", "=", "b.stall_id")
            ->where("p.status", "posted")
            ->whereNotExists(function ($q) {
                $q->selectRaw("1")
                    ->from("payment_reversals AS pr")
                    ->whereColumn("pr.payment_id", "p.id");
            })
            ->whereBetween("p.payment_date", [$from, $to]);
        if ($market) {
            $collections->where("s.market_id", $market);
        }
        $aging = [];
        foreach ([[0, 30], [31, 60], [61, 90], [91, null]] as $bracket) {
            $q = (clone $bills)
                ->whereRaw(self::balanceSql() . ">0")
                ->where("due_date", "<=", today()->subDays($bracket[0])->toDateString());
            if ($bracket[1] !== null) {
                $q->where("due_date", ">=", today()->subDays($bracket[1])->toDateString());
            }
            $aging[] = self::sum($q, self::balanceSql());
        }
        $tenants = Tenant::where("status", "active");
        if ($market) {
            $tenants->whereHas("assignments", function ($q) use ($market) {
                $q->where("status", "active")->whereHas("stall", function ($q) use ($market) {
                    $q->where("market_id", $market);
                });
            });
        }
        return [
            "counts" => $counts,
            "total" => $total,
            "occupancy" => $total ? round((100 * ($counts["occupied"] ?? 0)) / $total, 1) : 0,
            "activeTenants" => $tenants->count(),
            "billed" => self::sum($range, self::totalSql()),
            "collected" => bcadd((string) (clone $collections)->sum("pa.amount"), "0", 2),
            "outstanding" => self::sum($bills, self::balanceSql()),
            "overdue" => self::sum(
                (clone $bills)->where("due_date", "<", today()->toDateString()),
                self::balanceSql(),
            ),
            "aging" => $aging,
            "monthly" => (clone $collections)
                ->selectRaw("SUBSTR(p.payment_date,1,7) AS label, SUM(pa.amount) AS amount")
                ->groupByRaw("SUBSTR(p.payment_date,1,7)")
                ->orderBy("label")
                ->get(),
            "byMarket" => (clone $collections)
                ->join("markets AS m", "m.id", "=", "s.market_id")
                ->selectRaw("m.name AS label, SUM(pa.amount) AS amount")
                ->groupBy("m.id", "m.name")
                ->get(),
        ];
    }
}
