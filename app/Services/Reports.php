<?php
namespace App\Services;
use App\Models\{AuditLog, RentalOverride};
use Illuminate\Support\Facades\{DB, Schema};
class Reports
{
    public const TYPES = [
        "stall-master" => ["Stall Master List", "reports.view"],
        "occupied-stalls" => ["Occupied Stall Report", "reports.view"],
        "vacant-stalls" => ["Vacant Stall Report", "reports.view"],
        "tenant-master" => ["Tenant Master List", "reports.personal"],
        "assignment-history" => ["Stall Assignment History", "reports.personal"],
        "monthly-billing" => ["Rental Billing", "reports.financial"],
        "collection-summary" => ["Rental Collection Summary", "reports.view"],
        "daily-collections" => ["Daily Collections", "reports.view"],
        "collections-by-cashier" => ["Collections by Cashier", "reports.financial"],
        "collections-by-location" => ["Collections by Market / Section", "reports.view"],
        "receivables" => ["Outstanding Receivables", "reports.financial"],
        "overdue" => ["Overdue Rental Accounts", "reports.financial"],
        "tenant-statement" => ["Tenant Statement of Account", "reports.personal"],
        "payment-history" => ["Payment Transaction History", "reports.financial"],
        "rate-history" => ["Rental Rate History", "rates.view"],
        "audit-trail" => ["Audit Trail Report", "audit.view"],
        "business-categories" => ["Business Category Report", "rates.view"],
        "rental-assessments" => ["Rental Assessment Report", "reports.financial"],
        "rental-overrides" => ["Rental Override Report", "rentals.override.report"],
        "lease-contract-registry" => ["Lease Contract Registry", "contracts.view"],
    ];
    public static function scopeFilters($type): array
    {
        $filters = ['market_id'=>'Market','section_id'=>'Section','stall_id'=>'Stall','tenant_id'=>'Tenant','recorded_by'=>'Officer'];
        if (in_array($type, ['business-categories', 'audit-trail'], true)) {
            return [];
        }
        if ($type === 'rate-history') {
            return ['market_id'=>$filters['market_id']];
        }
        if ($type === 'tenant-master') {
            return array_intersect_key($filters, ['market_id'=>true, 'tenant_id'=>true]);
        }
        if (in_array($type, ['stall-master', 'occupied-stalls', 'vacant-stalls'], true)) {
            unset($filters['tenant_id']);
        }
        if (!in_array($type, ['legacy', 'collection-summary', 'daily-collections', 'collections-by-cashier',
            'collections-by-location', 'payment-history'], true)) {
            unset($filters['recorded_by']);
        }
        return $filters;
    }
    public static function query($type, array $f, bool $canSeeAssessmentMode = false)
    {
        if ($type === 'rental-overrides') {
            return self::overrideQuery($f);
        }
        $date = null;
        $marketColumn = null;
        $sectionColumn = null;
        $tenantColumn = null;
        $stallColumn = null;
        if (in_array($type, ["stall-master", "occupied-stalls", "vacant-stalls"])) {
            $q = DB::table("stalls AS s")
                ->join("markets AS m", "m.id", "=", "s.market_id")
                ->join("market_sections AS sec", "sec.id", "=", "s.market_section_id")
                ->selectRaw(
                    "s.code AS stall, m.name AS market, sec.name AS section, s.classification, s.area, s.status",
                );
            if ($type === "occupied-stalls") {
                $q->where("s.status", "occupied");
            }
            if ($type === "vacant-stalls") {
                $q->where("s.status", "vacant");
            }
            $marketColumn = "s.market_id";
            $sectionColumn = "s.market_section_id";
            $stallColumn = "s.id";
            $q->orderBy("m.name")->orderBy("s.code");
        } elseif ($type === "tenant-master") {
            $q = DB::table("tenants AS t")
                ->select("t.reference", "t.name", "t.phone", "t.email", "t.address", "t.status")
                ->orderBy("t.name");
            $tenantColumn = "t.id";
            if (!empty($f["market_id"])) {
                $q->whereExists(function ($q) use ($f) {
                    $q->selectRaw("1")
                        ->from("stall_assignments AS a")
                        ->join("stalls AS s", "s.id", "=", "a.stall_id")
                        ->whereColumn("a.tenant_id", "t.id")
                        ->where("s.market_id", $f["market_id"]);
                });
            }
        } elseif ($type === "assignment-history") {
            $q = DB::table("stall_assignments AS a")
                ->join("stalls AS s", "s.id", "=", "a.stall_id")
                ->join("tenants AS t", "t.id", "=", "a.tenant_id")
                ->leftJoin("rental_agreements AS ag", "ag.stall_assignment_id", "=", "a.id")
                ->selectRaw(
                    "s.code AS stall,t.reference AS tenant_reference,t.name AS tenant,ag.reference AS agreement,a.start_date,a.end_date,a.status",
                )
                ->orderBy("a.start_date");
            $date = "a.start_date";
            $marketColumn = "s.market_id";
            $sectionColumn = "s.market_section_id";
            $tenantColumn = "t.id";
            $stallColumn = "s.id";
        } elseif (
            in_array($type, ["monthly-billing", "receivables", "overdue", "tenant-statement"])
        ) {
            $q = DB::table("rental_bills AS b")
                ->join("stalls AS s", "s.id", "=", "b.stall_id")
                ->join("tenants AS t", "t.id", "=", "b.tenant_id")
                ->where("b.status", "posted")
                ->selectRaw(
                    "b.reference,t.reference AS tenant_reference,t.name AS tenant,s.code AS stall,b.period,b.due_date," .
                        Ledger::totalSql("b") .
                        " AS billed," .
                        Ledger::paidSql("b") .
                        " AS paid," .
                        Ledger::balanceSql("b") .
                        " AS outstanding",
                )
                ->orderBy("b.period")
                ->orderBy("b.id");
            if (Schema::hasColumn('rental_bills', 'coverage_start')) {
                $q->leftJoin('rental_assessments AS ra', 'ra.id', '=', 'b.rental_assessment_id')
                    ->addSelect('b.coverage_start', 'b.coverage_end')
                    ->selectRaw('CASE WHEN b.coverage_start IS NOT NULL THEN ra.monthly_cusa END AS record_only_cusa');
            }
            if (in_array($type, ["receivables", "overdue"])) {
                $q->whereRaw(Ledger::balanceSql("b") . ">0");
            }
            if ($type === "overdue") {
                $q->where("b.due_date", "<", today()->toDateString());
            }
            $date = "b.due_date";
            $marketColumn = "s.market_id";
            $sectionColumn = "s.market_section_id";
            $tenantColumn = "t.id";
            $stallColumn = "s.id";
        } elseif (
            in_array($type, [
                "collection-summary",
                "daily-collections",
                "collections-by-cashier",
                "collections-by-location",
            ])
        ) {
            $q = DB::table("payment_allocations AS pa")
                ->join("payments AS p", "p.id", "=", "pa.payment_id")
                ->join("rental_bills AS b", "b.id", "=", "pa.rental_bill_id")
                ->join("stalls AS s", "s.id", "=", "b.stall_id")
                ->join("markets AS m", "m.id", "=", "s.market_id")
                ->join("market_sections AS sec", "sec.id", "=", "s.market_section_id")
                ->join("users AS u", "u.id", "=", "p.recorded_by")
                ->where("p.status", "posted")
                ->whereNotExists(function ($q) {
                    $q->selectRaw("1")
                        ->from("payment_reversals AS pr")
                        ->whereColumn("pr.payment_id", "p.id");
                });
            $group = [
                "collection-summary" => ["SUBSTR(p.payment_date,1,7)", "period"],
                "daily-collections" => ["p.payment_date", "payment_date"],
                "collections-by-cashier" => ["u.name", "officer"],
                "collections-by-location" => ["m.name", "market"],
            ][$type];
            $q->selectRaw(
                $group[0] .
                    " AS " .
                    $group[1] .
                    ",SUM(pa.amount) AS collected,SUM(CASE WHEN p.reconciliation_status='confirmed' THEN pa.amount ELSE 0 END) AS treasury_confirmed",
            )
                ->groupByRaw($group[0])
                ->orderBy($group[1]);
            if ($type === "collections-by-location") {
                $q->addSelect("sec.name AS section")->groupBy("sec.name", "m.id", "sec.id");
            }
            if ($type === "collections-by-cashier") {
                $q->groupBy("u.id");
            }
            $date = "p.payment_date";
            $marketColumn = "s.market_id";
            $sectionColumn = "s.market_section_id";
            $tenantColumn = "p.tenant_id";
            $stallColumn = "s.id";
        } elseif ($type === "payment-history") {
            $q = DB::table("payments AS p")
                ->join("tenants AS t", "t.id", "=", "p.tenant_id")
                ->join("users AS u", "u.id", "=", "p.recorded_by")
                ->leftJoin("payment_reversals AS pr", "pr.payment_id", "=", "p.id")
                ->selectRaw(
                    "p.reference,p.payment_date,t.name AS tenant,p.amount,p.method,p.receipt_reference,u.name AS recorded_by,CASE WHEN pr.id IS NOT NULL THEN 'reversed' ELSE p.status END AS status,p.reconciliation_status",
                )
                ->orderBy("p.payment_date")
                ->orderBy("p.id");
            $date = "p.payment_date";
            $tenantColumn = "p.tenant_id";
            foreach (
                [
                    "market_id" => "s.market_id",
                    "section_id" => "s.market_section_id",
                    "stall_id" => "s.id",
                ]
                as $key => $column
            ) {
                if (!empty($f[$key])) {
                    $q->whereExists(function ($q) use ($column, $f, $key) {
                        $q->selectRaw("1")
                            ->from("payment_allocations AS pa")
                            ->join("rental_bills AS b", "b.id", "=", "pa.rental_bill_id")
                            ->join("stalls AS s", "s.id", "=", "b.stall_id")
                            ->whereColumn("pa.payment_id", "p.id")
                            ->where($column, $f[$key]);
                    });
                }
            }
            if (!empty($f["status"])) {
                if ($f["status"] === "reversed") {
                    $q->whereNotNull("pr.id");
                } else {
                    $q->where("p.status", $f["status"])->whereNull("pr.id");
                }
            }
        } elseif ($type === 'business-categories') {
            $q = DB::table('business_categories AS c')
                ->leftJoin('business_category_rental_rates AS r',function ($join) {
                    $join->on('r.business_category_id','=','c.id');
                })
                ->selectRaw('c.code,c.name,c.description,r.minimum_rate AS inner_minimum,r.maximum_rate AS outer_maximum,r.effective_from,r.effective_until,r.status AS rate_status,r.reference_document,c.is_active,(SELECT COUNT(*) FROM businesses b WHERE b.business_category_id=c.id) AS registered_business_count,(SELECT COUNT(*) FROM stall_assignments sa JOIN businesses b ON b.id=sa.business_id WHERE b.business_category_id=c.id AND sa.status=?) AS active_stall_count',['active'])
                ->orderBy('c.sort_order')->orderBy('c.name')->orderBy('r.effective_from');
            if (!empty($f['business_category_id'])) {
                $q->where('c.id',$f['business_category_id']);
            }
            $date = 'r.effective_from';
        } elseif ($type === 'rental-assessments') {
            $sectionSnapshot = DB::connection()->getQueryGrammar()->wrap('ra.basis->stall_section_name');
            $fixedDays = 'LENGTH(ra.period) = 10';
            $end = DB::connection()->getDriverName() === 'sqlite'
                ? "DATE(ra.period, '+' || (ra.billing_days - 1) || ' days')"
                : 'DATE_ADD(ra.period, INTERVAL (ra.billing_days - 1) DAY)';
            $q = DB::table('rental_assessments AS ra')
                ->join('stall_assignments AS a','a.id','=','ra.stall_assignment_id')
                ->join('stalls AS s','s.id','=','a.stall_id')
                ->selectRaw('ra.id AS assessment,ra.period,ra.purpose,a.tenant_id,ra.market_name AS assessed_market,ra.stall_code AS assessed_stall,ra.business_category_name,ra.location_type,ra.floor_area,ra.minimum_rate,ra.maximum_rate,ra.rental_rate_sqm AS applied_rate,ra.daily_rental,ra.billing_days,ra.monthly_rental AS period_rent,ra.daily_cusa,ra.monthly_cusa AS period_cusa,ra.total_monthly_rental_cusa AS period_payable,ra.business_category_rental_rate_id AS rate_schedule,ra.rental_policy_id AS policy,ra.created_at')
                ->selectRaw("CASE WHEN {$fixedDays} THEN ra.period END AS coverage_start, CASE WHEN {$fixedDays} THEN {$end} END AS coverage_end, CASE WHEN {$fixedDays} THEN 'Records only' ELSE 'Chargeable' END AS cusa_treatment")
                ->selectRaw("COALESCE(ra.market_section_name, {$sectionSnapshot}) AS assessed_section")
                ->orderByDesc('ra.id');
            if ($canSeeAssessmentMode) {
                $q->addSelect('ra.computation_mode', 'ra.rental_difference')
                    ->selectRaw('COALESCE(ra.automatic_daily_rental, ra.daily_rental) AS automatic_daily_rent');
            }
            $marketColumn = 'ra.market_id'; $tenantColumn = 'a.tenant_id'; $stallColumn = 'a.stall_id'; $date = 'ra.created_at';
            if (!empty($f['section_id'])) {
                $q->whereRaw('COALESCE(ra.market_section_id, s.market_section_id) = ?', [(int) $f['section_id']]);
            }
            if (!empty($f['computation_mode'])) {
                $q->where('ra.computation_mode', $f['computation_mode']);
            }
            if (!empty($f['as_of'])) {
                $q->where('ra.created_at', '<=', $f['as_of'].' 23:59:59');
            }
            if (!empty($f['business_category_id'])) { $q->where('ra.business_category_id',$f['business_category_id']); }
            if (!empty($f['location_type'])) {
                $f['location_type']==='unclassified' ? $q->whereNull('ra.location_type') : $q->where('ra.location_type',$f['location_type']);
            }
        } elseif ($type === 'lease-contract-registry') {
            $q = DB::table('lease_contracts AS c')
                ->join('stall_assignments AS a','a.id','=','c.stall_assignment_id')
                ->join('stalls AS s','s.id','=','a.stall_id')
                ->leftJoin('rental_assessments AS ra','ra.id','=','c.rental_assessment_id')
                ->selectRaw('c.control_number,c.tenant_id,COALESCE(ra.stall_code,s.code) AS stall,ra.business_category_name,ra.location_type,c.start_date,c.end_date,c.status,c.rental_rate_sqm,c.daily_rental,c.billing_days,c.monthly_rental,c.monthly_cusa,c.total_monthly_rental_cusa,c.generated_at,c.issued_at,c.visual_review_reference')
                ->orderByDesc('c.id');
            if (!empty($f['status'])) { $q->where('c.status',$f['status']); }
            $marketColumn = 's.market_id'; $sectionColumn = 's.market_section_id'; $tenantColumn = 'c.tenant_id'; $stallColumn = 's.id'; $date = 'c.start_date';
            if (!empty($f['business_category_id'])) { $q->where('ra.business_category_id',$f['business_category_id']); }
            if (!empty($f['location_type'])) {
                $f['location_type']==='unclassified' ? $q->whereNull('ra.location_type') : $q->where('ra.location_type',$f['location_type']);
            }
        } elseif ($type === "rate-history") {
            $q = DB::table("rental_rates AS r")
                ->join("markets AS m", "m.id", "=", "r.market_id")
                ->selectRaw(
                    "m.name AS market,r.classification,r.amount,r.effective_from,r.effective_until,r.approval_reference,r.status",
                )
                ->orderBy("r.effective_from");
            $date = "r.effective_from";
            $marketColumn = "r.market_id";
        } else {
            $q = AuditLog::visibleTo(auth()->user())->toBase()->from('audit_logs AS a')
                ->leftJoin("users AS u", "u.id", "=", "a.actor_id")
                ->selectRaw(
                    "a.created_at,u.name AS actor,a.action,a.entity_type,a.entity_id,a.reason",
                )
                ->orderByDesc("a.id");
            $date = "a.created_at";
        }
        foreach (
            [
                "market_id" => $marketColumn,
                "section_id" => $sectionColumn,
                "tenant_id" => $tenantColumn,
                "stall_id" => $stallColumn,
            ]
            as $key => $col
        ) {
            if ($col && !empty($f[$key])) {
                $q->where($col, $f[$key]);
            }
        }
        if ($date) {
            if (!empty($f["from"])) {
                $q->where($date, ">=", $f["from"]);
            }
            if (!empty($f["to"])) {
                $q->where($date, "<=", $f["to"] . (in_array($type,['audit-trail','rental-assessments'],true) ? " 23:59:59" : ""));
            }
        }
        if (
            !empty($f["recorded_by"]) &&
            (strpos($type, "collection") !== false || $type === "payment-history")
        ) {
            $q->where("p.recorded_by", $f["recorded_by"]);
        }
        return $q;
    }

    private static function overrideQuery(array $filters)
    {
        $day = $filters['as_of'] ?? today()->toDateString();
        $covering = RentalOverride::covering($day, $day)
            ->selectRaw('stall_assignment_id, MIN(id) AS id, COUNT(*) AS approval_count')
            ->groupBy('stall_assignment_id');
        $sectionSnapshot = DB::connection()->getQueryGrammar()->wrap('ra.basis->stall_section_name');
        $authorityReference = DB::connection()->getQueryGrammar()->wrap('ro.authority_snapshot->override_authority_reference');
        $term = '(a.start_date <= ? AND (a.end_date IS NULL OR a.end_date >= ?))';
        $mode = "CASE WHEN {$term} AND covered.approval_count = 1 AND covered.id = ro.id THEN 'manual_override'
            WHEN {$term} AND covered.id IS NULL THEN 'automatic' END";
        $state = "CASE WHEN ro.status = 'revoked' AND ro.revocation_effective_from <= ? THEN 'revoked'
            WHEN ro.status IN ('approved', 'revoked') AND (ro.effective_until < ? OR a.end_date < ?) THEN 'expired'
            WHEN ro.status = 'revoked' THEN 'approved' ELSE ro.status END";
        $source = DB::table('rental_overrides AS ro')
            ->join('stall_assignments AS a', 'a.id', '=', 'ro.stall_assignment_id')
            ->join('tenants AS t', 't.id', '=', 'a.tenant_id')
            ->join('rental_assessments AS ra', 'ra.id', '=', 'ro.initial_assessment_id')
            ->join('stalls AS s', 's.id', '=', 'a.stall_id')
            ->leftJoin('businesses AS b', 'b.id', '=', 'a.business_id')
            ->leftJoin('users AS reviewer', 'reviewer.id', '=', 'ro.approved_by')
            ->leftJoinSub($covering->toBase(), 'covered', 'covered.stall_assignment_id', '=', 'a.id')
            ->selectRaw("ro.id AS request, t.name AS tenant, b.name AS business, ra.stall_code AS stall,
                COALESCE(ra.market_section_name, {$sectionSnapshot}) AS assessed_section,
                ra.business_category_name AS business_category, ra.floor_area, ro.override_type AS method,
                ro.automatic_daily_rental AS automatic_daily_rent, ro.proposed_daily_rental AS proposed_daily_rent,
                ro.rental_difference AS proposed_daily_difference, ro.reason, ro.reference_document AS supporting_reference,
                reviewer.name AS approved_by, ro.rental_policy_id AS authorizing_policy, {$authorityReference} AS exception_authority_reference,
                ro.effective_from, ro.effective_until, ro.revocation_effective_from")
            ->selectRaw("{$mode} AS computation_mode, {$state} AS status", [$day, $day, $day, $day, $day, $day, $day]);
        foreach (['market_id'=>'ra.market_id', 'stall_id'=>'a.stall_id', 'tenant_id'=>'a.tenant_id', 'business_category_id'=>'ra.business_category_id'] as $filter=>$column) {
            if (!empty($filters[$filter])) {
                $source->where($column, $filters[$filter]);
            }
        }
        if (!empty($filters['section_id'])) {
            $source->whereRaw('COALESCE(ra.market_section_id, s.market_section_id) = ?', [(int) $filters['section_id']]);
        }
        $query = DB::query()->fromSub($source, 'requests')
            ->select('requests.*')
            ->selectRaw("CASE computation_mode WHEN 'manual_override' THEN proposed_daily_rent WHEN 'automatic' THEN automatic_daily_rent END AS applicable_daily_rent")
            ->selectRaw("CASE computation_mode WHEN 'manual_override' THEN proposed_daily_difference WHEN 'automatic' THEN '0.00' END AS applicable_daily_difference")
            ->orderByDesc('request');
        foreach (['status', 'computation_mode'] as $filter) {
            if (!empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }
        return $query;
    }

    public static function csvCell($value)
    {
        $value = (string) $value;
        return preg_match("/^[\s]*[=+@\-]/u", $value) ? "'" . $value : $value;
    }
}
