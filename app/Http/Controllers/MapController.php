<?php
namespace App\Http\Controllers;
use App\Models\{Market, MarketLayout, Stall, StallLayoutPosition, Payment};
use App\Services\Audit;
use App\Support\AssignmentRental;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class MapController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize("map.view");
        if ($request->filled('business_category_id')) {
            $this->authorize('tenants.view');
        }
        $canSeeComputation = $this->canSeeComputation($request);
        abort_if($request->anyFilled(['period','computation_mode']) && !$canSeeComputation, 403);
        $filters = $request->validate([
            "market_id" => "nullable|integer|exists:markets,id",
            "section_id" => "nullable|integer",
            "status" => "nullable|in:occupied,vacant,reserved,maintenance,inactive",
            "location_type" => "nullable|in:inner,outer,unclassified",
            "business_category_id" => "nullable|integer|exists:business_categories,id",
            "period" => "nullable|date_format:Y-m",
            "computation_mode" => "nullable|in:automatic,manual_override",
        ]);
        $period = $filters['period'] ?? today()->format('Y-m');
        $markets = Market::orderBy("name")->get();
        $market = $markets->firstWhere("id", (int) $request->market_id) ?: $markets->first();
        $stalls = collect();
        $sections = collect();
        $sectionStalls = collect();
        $selectedSection = null;
        $layout = null;
        if ($market) {
            $sections = $market->sections()->inDisplayOrder()->get();
            $q = $market->stalls()->with("position", "section");
            $selectedSection = $sections->firstWhere('id', (int) $request->input('section_id'));
            if ($selectedSection) {
                $q->where('market_section_id', $selectedSection->id);
            }
            if ($request->filled("status")) {
                $q->where("status", $request->status);
            }
            if ($request->input('location_type') === 'unclassified') {
                $q->whereNull('location_type');
            } elseif ($request->filled('location_type')) {
                $q->where('location_type',$request->input('location_type'));
            }
            if ($request->filled('business_category_id')) {
                $q->whereHas('currentAssignment.business',function ($q) use ($request) { $q->where('business_category_id',$request->input('business_category_id')); });
            }
            if (isset($filters['computation_mode'])) {
                $first = $period.'-01';
                $last = Carbon::parse($first)->endOfMonth()->toDateString();
                $q->whereHas('currentAssignment', function ($assignment) use ($filters, $first, $last) {
                    $assignment->where('start_date', '<=', $last)
                        ->where(fn ($term) => $term->whereNull('end_date')->orWhere('end_date', '>=', $first))
                        ->whereHas('agreement.assessment', fn ($assessment) => $assessment->where('purpose', 'initial')
                            ->whereColumn('rental_assessments.stall_assignment_id', 'stall_assignments.id'));
                    $coverage = fn ($overrides) => $overrides->covering($first, $first);
                    if ($filters['computation_mode'] === 'manual_override') {
                        $assignment->whereHas('rentalOverrides', $coverage);
                    } else {
                        $assignment->whereDoesntHave('rentalOverrides', $coverage);
                    }
                });
            }
            $stalls = $q->orderBy("code")->get();
            $sectionStalls = $stalls->groupBy('market_section_id');
            $layout = MarketLayout::where("market_id", $market->id)->first();
        }
        $businessCategories = $request->user()->can('tenants.view') ? \App\Models\BusinessCategory::orderBy('name')->get() : collect();
        return view('map.index', compact('markets', 'market', 'stalls', 'layout', 'businessCategories', 'sections', 'sectionStalls',
            'selectedSection', 'canSeeComputation', 'period'));
    }
    public function details(Request $request, Stall $stall)
    {
        $this->authorize("view", $stall);
        $canSeeComputation = $this->canSeeComputation($request);
        abort_if($request->filled('period') && !$canSeeComputation, 403);
        $period = $request->validate(['period'=>'nullable|date_format:Y-m'])['period'] ?? today()->format('Y-m');
        $stall->load("market", "section", "category");
        $assignment = null;
        $history = collect();
        $payments = collect();
        $balance = "0.00";
        if ($request->user()->can("tenants.view")) {
            $assignment = $stall
                ->currentAssignment()
                ->with("tenant", "business.businessCategory", "agreement.assessment")
                ->first();
            $history = $stall
                ->assignments()
                ->with("tenant")
                ->latest("start_date")
                ->limit(10)
                ->get();
        }
        if ($request->user()->can("billing.view")) {
            $q = \App\Services\Ledger::bills()->where("stall_id", $stall->id);
            $balance = \App\Services\Ledger::sum($q, \App\Services\Ledger::balanceSql());
        }
        if ($request->user()->can("payments.view")) {
            $payments = Payment::effective()
                ->whereHas("allocations.bill", function ($q) use ($stall) {
                    $q->where("stall_id", $stall->id);
                })
                ->latest("payment_date")
                ->limit(5)
                ->get();
        }
        $assessment = $assignment ? AssignmentRental::initial($assignment) : null;
        [$quote, $quoteError] = $assignment && $canSeeComputation ? AssignmentRental::quote($assignment, $period) : [null, null];
        $lease = $assignment && $request->user()->can('contracts.view') ? \App\Models\LeaseContract::where('stall_assignment_id',$assignment->id)->latest('id')->first() : null;
        return response()->view(
            "map.details",
            compact("stall", "assignment", "history", "payments", "balance", "assessment", "lease", "canSeeComputation", "period", "quote", "quoteError"),
        )->header('Cache-Control','private, no-store');
    }
    private function canSeeComputation(Request $request): bool
    {
        $user = $request->user();
        return $user->can('tenants.view') && $user->can('billing.view') && $user->can('rentals.override.view');
    }
    public function editor(Market $market)
    {
        $this->authorize("markets.manage");
        return view("map.editor", [
            "market" => $market,
            "layout" => MarketLayout::where("market_id", $market->id)->first(),
            "stalls" => $market->stalls()->with("position")->orderBy("code")->get(),
        ]);
    }
    public function save(Request $request, Market $market)
    {
        $this->authorize("markets.manage");
        $d = $request->validate([
            "columns" => "required|integer|min:1|max:40",
            "rows" => "required|integer|min:1|max:100",
            "positions" => "required|array|max:2000",
            "positions.*.stall_id" => "required|integer|distinct",
            "positions.*.row" => "required|integer|min:1",
            "positions.*.column" => "required|integer|min:1",
            "positions.*.width" => "required|integer|min:1|max:10",
            "positions.*.height" => "required|integer|min:1|max:10",
        ]);
        DB::transaction(function () use ($request, $market, $d) {
            Market::whereKey($market->id)->lockForUpdate()->firstOrFail();
            $ids = $market->stalls()->pluck("id")->all();
            $received = array_column($d["positions"], "stall_id");
            sort($ids);
            sort($received);
            if ($ids != $received) {
                throw ValidationException::withMessages([
                    "positions" => "Submit every stall in this market exactly once.",
                ]);
            }
            $occupied = [];
            foreach ($d["positions"] as $p) {
                if (
                    $p["row"] + $p["height"] - 1 > $d["rows"] ||
                    $p["column"] + $p["width"] - 1 > $d["columns"]
                ) {
                    throw ValidationException::withMessages([
                        "positions" => "A stall extends beyond the layout.",
                    ]);
                }
                for ($r = $p["row"]; $r < $p["row"] + $p["height"]; $r++) {
                    for ($c = $p["column"]; $c < $p["column"] + $p["width"]; $c++) {
                        if (isset($occupied[$r . ":" . $c])) {
                            throw ValidationException::withMessages([
                                "positions" => "Stalls cannot overlap.",
                            ]);
                        }
                        $occupied[$r . ":" . $c] = true;
                    }
                }
            }
            $layout = MarketLayout::updateOrCreate(
                ["market_id" => $market->id],
                [
                    "name" => $market->name . " layout",
                    "columns" => $d["columns"],
                    "rows" => $d["rows"],
                ],
            );
            foreach ($d["positions"] as $p) {
                StallLayoutPosition::updateOrCreate(
                    ["stall_id" => $p["stall_id"]],
                    [
                        "market_layout_id" => $layout->id,
                        "row" => $p["row"],
                        "column" => $p["column"],
                        "width" => $p["width"],
                        "height" => $p["height"],
                    ],
                );
            }
            Audit::record(
                $request->user(),
                "layout.updated",
                $layout,
                [],
                ["positions" => $received],
            );
        }, 3);
        return redirect()
            ->route("map", ["market_id" => $market->id])
            ->with("success", "Layout saved.");
    }
}
