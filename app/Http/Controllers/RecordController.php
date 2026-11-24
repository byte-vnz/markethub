<?php
namespace App\Http\Controllers;
use App\Support\Resources;
use App\Http\Requests\RecordRequest;
use App\Models\{
    Market,
    MarketSection,
    Stall,
    Tenant,
    Business,
    RentalRate,
    RentalAgreement,
    StallAssignment,
};
use App\Services\{Audit, SectionService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class RecordController extends Controller
{
    public function index(Request $request, $type)
    {
        if ($type === 'tenants') {
            return app(TenantController::class)->index($request);
        }
        if ($type === 'businesses') {
            $this->authorize('tenants.view');
            return redirect()->route('tenant-businesses.index', $request->only('q','status','tenant_id'));
        }
        $spec = Resources::get($type);
        $this->authorize($spec["permission"] . ".view");
        if ($type === 'sections') {
            return $this->sections($request);
        }
        $q = $spec["model"]::query();
        if ($type === 'rates') {
            $q->with('market:id,name');
        }
        $stallSections = collect();
        if ($type === 'stalls') {
            if ($request->filled('business_category_id')) {
                $this->authorize('tenants.view');
            }
            $request->validate(['q'=>'nullable|string|max:100','location_type'=>'nullable|in:inner,outer,unclassified','business_category_id'=>'nullable|integer|exists:business_categories,id',
                'market_id'=>'nullable|integer|exists:markets,id', 'section_id'=>'nullable|integer|exists:market_sections,id']);
            $q->with(['market','section','category','currentAssignment.tenant','currentAssignment.business.businessCategory','currentAssignment.agreement.assessment']);
            $stallSections = MarketSection::with('market')->inDisplayOrder()->when($request->filled('market_id'), function ($sections) use ($request) {
                $sections->where('market_id', $request->input('market_id'));
            })->get();
            $selectedSection = $stallSections->firstWhere('id', (int) $request->input('section_id'));
            if ($selectedSection) {
                $q->where('market_section_id', $selectedSection->id);
            }
            if ($request->input('location_type') === 'unclassified') {
                $q->whereNull('location_type');
            } elseif ($request->filled('location_type')) {
                $q->where('location_type',$request->input('location_type'));
            }
            if ($request->filled('business_category_id')) {
                $q->whereHas('currentAssignment.business',function ($q) use ($request) { $q->where('business_category_id',$request->input('business_category_id')); });
            }
        }
        if ($request->filled("q")) {
            $q->where($spec["search"], "like", "%" . substr($request->q, 0, 100) . "%");
        }
        if ($request->filled("market_id") && isset($spec["fields"]["market_id"])) {
            $q->where("market_id", $request->market_id);
        }
        if ($request->filled("status") && isset($spec["fields"]["status"])) {
            $q->where("status", $request->status);
        }
        return view($type === 'stalls' ? 'stalls.index' : 'records.index', [
            "spec" => $spec,
            "type" => $type,
            "records" => $q->orderByDesc("id")->paginate(20)->withQueryString(),
            "businessCategories" => $type === 'stalls' && $request->user()->can('tenants.view') ? \App\Models\BusinessCategory::orderBy('name')->get() : collect(),
            'stallMarkets' => $type === 'stalls' ? Market::orderBy('name')->get() : collect(),
            'stallSections' => $stallSections,
        ]);
    }

    private function sections(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100', 'status' => 'nullable|in:active,inactive',
            'market_id' => 'nullable|integer|exists:markets,id',
        ]);
        $query = MarketSection::with('market')->withStallCounts()->orderBy('market_id')->inDisplayOrder();
        if (!empty($filters['market_id'])) {
            $query->where('market_id', $filters['market_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($request->filled('q')) {
            $query->where(function ($sections) use ($filters) {
                $match = '%'.$filters['q'].'%';
                $sections->where('name', 'like', $match)->orWhere('code', 'like', $match);
            });
        }
        $records = $query->paginate(20)->withQueryString();
        $markets = Market::orderBy('name')->get();
        $orderable = collect();
        if (!empty($filters['market_id']) && !$request->anyFilled(['q', 'status']) && $request->user()->can('sections.manage')) {
            $orderable = MarketSection::where('market_id', $filters['market_id'])->inDisplayOrder()->limit(SectionService::MAX_REORDER_SECTIONS + 1)->get();
        }
        return view('sections.index', compact('records', 'markets', 'filters', 'orderable'));
    }

    public function reorderSections(Request $request, SectionService $service)
    {
        $this->authorize('sections.manage');
        $data = $request->validate(['market_id' => 'required|integer', 'ids' => 'required|array|max:'.SectionService::MAX_REORDER_SECTIONS]);
        $service->reorder($request->user(), $data['market_id'], $data['ids']);
        return redirect()->route('records.index', ['type' => 'sections', 'market_id' => $data['market_id']])
            ->with('success', 'Section order saved.');
    }

    public function stallSectionOptions(Market $market)
    {
        $this->authorize('stalls.manage');
        return response()->json($market->sections()->where('status', 'active')->inDisplayOrder()->get(['id', 'name', 'code']))
            ->header('Cache-Control', 'private, no-store');
    }
    public function create($type)
    {
        if ($type === 'tenants') {
            return app(TenantController::class)->create();
        }
        if ($type === 'businesses') {
            $this->authorize('tenants.manage');
            if (request()->filled('tenant_id')) {
                return redirect()->route('tenant-businesses.create',Tenant::findOrFail(request('tenant_id')));
            }
            return redirect()->route('tenant-businesses.index');
        }
        $spec = Resources::get($type);
        $this->authorize($spec["permission"] . ".manage");
        $record = new ($spec["model"])();
        if (in_array($type, ['sections', 'stalls'], true) && is_numeric(request('market_id'))) {
            $record->market_id = (int) request('market_id');
        }
        return $this->form($type, $record);
    }
    public function edit($type, $id)
    {
        if ($type === 'tenants') {
            return app(TenantController::class)->edit(request(), $id);
        }
        if ($type === 'businesses') {
            $this->authorize('tenants.manage');
            $business = Business::findOrFail($id);
            return $business->tenant ? redirect()->route('tenant-businesses.edit',[$business->tenant,$business]) : redirect()->route('tenant-businesses.index',['orphaned'=>1]);
        }
        $spec = Resources::get($type);
        $model = $spec["model"]::findOrFail($id);
        $this->authorize("update", $model);
        return $this->form($type, $model);
    }
    private function form($type, $record)
    {
        if ($type === 'sections') {
            $markets = Market::orderBy('name')->get();
            $fromStall = !$record->exists && request()->boolean('from_stall');
            return view('sections.form', compact('record', 'markets', 'fromStall'));
        }
        $spec = Resources::get($type);
        $options = [];
        $retainedSection = null;
        foreach ($spec["fields"] as $field => $meta) {
            if ($type === 'stalls' && $field === 'market_section_id') {
                $marketId = old('market_id', $record->market_id);
                $options[$field] = is_numeric($marketId)
                    ? MarketSection::where('market_id', $marketId)->where('status', 'active')->inDisplayOrder()->get()
                    : collect();
                $current = $record->exists ? $record->section : null;
                if ($current && $current->status === 'inactive' && (int) $current->market_id === (int) $record->market_id) {
                    $retainedSection = $current;
                }
            } elseif ($meta[1] === "relation") {
                $options[$field] = $meta[2]::orderBy("id")->get();
            }
        }
        return view("records.form", compact("type", "spec", "record", "options", 'retainedSection'));
    }
    public function store(RecordRequest $request, $type)
    {
        return $this->save($request, $type, null);
    }
    public function update(RecordRequest $request, $type, $id)
    {
        return $this->save($request, $type, $id);
    }
    private function save($request, $type, $id)
    {
        if ($type === 'tenants') {
            return app(TenantController::class)->save($request, $id);
        }
        if ($type === 'businesses') {
            $business = app(\App\Services\BusinessService::class)->save($request->user(),$request->validated(),$id);
            return redirect()->route('records.show',['type'=>'tenants','id'=>$business->tenant_id,'tab'=>'businesses'])->with('success','Business saved in Tenant Management.');
        }
        $spec = Resources::get($type);
        $d = $request->validated();
        if ($type === 'sections') {
            $section = app(SectionService::class)->save($request->user(), $d, $id);
            if ($id === null && $request->boolean('from_stall')) {
                return redirect()->route('records.show', ['type' => 'sections', 'id' => $section->id, 'from_stall' => 1])
                    ->with('success', 'Section saved. Return to the stall tab and refresh the section choices.');
            }
            return redirect()->route('records.index', ['type' => 'sections', 'market_id' => $section->market_id])
                ->with('success', 'Section saved.');
        }
        $reason = $d['change_reason'] ?? null;
        unset($d['change_reason']);
        $originalStallMarket = $type === 'stalls' && $id ? Stall::findOrFail($id)->market_id : null;
        $record = DB::transaction(function () use ($spec, $d, $id, $type, $request, $reason, $originalStallMarket) {
            // Lock parents before child writes, matching occupancy service lock order.
            if (isset($d["tenant_id"])) {
                Tenant::whereKey($d["tenant_id"])->lockForUpdate()->firstOrFail();
            }
            if ($type === 'stalls') {
                Market::whereIn('id', array_filter([$originalStallMarket, $d['market_id']]))->orderBy('id')->lockForUpdate()->get();
            } elseif (isset($d["market_id"])) {
                Market::whereKey($d["market_id"])->lockForUpdate()->firstOrFail();
            }
            $m = $id
                ? $spec["model"]::whereKey($id)->lockForUpdate()->firstOrFail()
                : new ($spec["model"])();
            $old = $m->getAttributes();
            if ($id) {
                $this->authorize("update", $m);
            }
            if ($type === "stalls") {
                if ($id && (int) $originalStallMarket !== (int) $m->market_id) {
                    throw ValidationException::withMessages(['market_id' => 'The stall market changed. Reload the record and try again.']);
                }
                $section = MarketSection::whereKey($d['market_section_id'])->where('market_id', $d['market_id'])->lockForUpdate()->first();
                $retainingSection = $id && (int) $m->market_id === (int) $d['market_id']
                    && (int) $m->market_section_id === (int) $d['market_section_id'];
                if (!$section || ($section->status !== 'active' && !$retainingSection)) {
                    throw ValidationException::withMessages(['market_section_id' => 'Choose an active section in this market.']);
                }
                if ($id && $m->assignments()->exists()) {
                    if (bccomp((string)$m->area,(string)$d['area'],2) !== 0 || (array_key_exists('location_type',$d) && $m->location_type !== $d['location_type'])) {
                        $this->check(is_string($reason) && mb_strlen(trim($reason)) >= 5,'Explain the verified area or location correction. Historical assessments will retain their original values.');
                    }
                    $this->check(
                        $m->market_id == $d["market_id"] &&
                            $m->market_section_id == $d["market_section_id"] &&
                            $m->classification === $d["classification"],
                        "Stalls with history cannot move market/section or change classification.",
                    );
                }
                if ($id && $m->currentAssignment) {
                    $this->check(
                        $d["status"] === "occupied",
                        "End the active assignment before changing occupancy.",
                    );
                }
                $d["created_by"] = $id ? $m->created_by : $request->user()->id;
                $d["updated_by"] = $request->user()->id;
            }
            if ($type === "tenants" && ($d["status"] ?? "") === "inactive") {
                $this->check(
                    !StallAssignment::where("tenant_id", $id)->where("status", "active")->exists(),
                    "End active assignments before archiving this tenant.",
                );
            }
            if ($type === 'markets' && ($d['status'] ?? '') === 'inactive') {
                $q = Stall::where('market_id', $id)->where('status', 'occupied');
                $this->check(
                    !$q->exists(),
                    "End occupied assignments before deactivating this location.",
                );
            }
            if ($type === "rates") {
                if ($id && RentalAgreement::where("rental_rate_id", $id)->exists()) {
                    $this->check(
                        $m->market_id == $d["market_id"] &&
                            $m->classification === $d["classification"] &&
                            bccomp((string) $m->amount, (string) $d["amount"], 2) === 0 &&
                            $m->effective_from === $d["effective_from"] &&
                            $m->approval_reference === $d["approval_reference"],
                        "Referenced rates are immutable. Create a new effective rate.",
                    );
                    if (!empty($d["effective_until"])) {
                        $this->check(
                            !RentalAgreement::where("rental_rate_id", $id)
                                ->where("start_date", ">", $d["effective_until"])
                                ->exists(),
                            "Rate end precedes a linked agreement.",
                        );
                    }
                }
                if ($d["status"] === "active") {
                    $q = RentalRate::where("market_id", $d["market_id"])
                        ->where("classification", $d["classification"])
                        ->where("status", "active")
                        ->where("effective_from", "<=", $d["effective_until"] ?? "9999-12-31")
                        ->where(function ($q) use ($d) {
                            $q->whereNull("effective_until")->orWhere(
                                "effective_until",
                                ">=",
                                $d["effective_from"],
                            );
                        });
                    if ($id) {
                        $q->where("id", "!=", $id);
                    }
                    $this->check(
                        !$q->exists(),
                        "An active rate overlaps these effective dates. End or deactivate it first.",
                    );
                }
            }
            $m->fill($d)->save();
            Audit::record($request->user(), $type . ($id ? ".updated" : ".created"), $m, $old, $d, $reason);
            return $m;
        }, 3);
        return redirect()->route("records.index", $type)->with("success", "Record saved.");
    }
    public function show($type, $id)
    {
        if ($type === 'tenants') {
            return app(TenantController::class)->show($id);
        }
        if ($type === 'businesses') {
            $this->authorize('tenants.view');
            $business = Business::findOrFail($id);
            return $business->tenant ? redirect()->route('records.show',['type'=>'tenants','id'=>$business->tenant_id,'tab'=>'businesses']) : redirect()->route('tenant-businesses.index',['orphaned'=>1]);
        }
        $spec = Resources::get($type);
        if ($type === 'sections') {
            $this->authorize('sections.view');
            $record = MarketSection::with('market')->withStallCounts()->findOrFail($id);
            return view('sections.show', compact('record'));
        }
        $record = $spec["model"]::findOrFail($id);
        $this->authorize("view", $record);
        if ($type === 'stalls') {
            $record->load('currentAssignment.tenant','currentAssignment.business.businessCategory','currentAssignment.agreement.assessment');
        }
        return view("records.show", compact("type", "spec", "record"));
    }
    private function check($ok, $message)
    {
        if (!$ok) {
            throw ValidationException::withMessages(["record" => $message]);
        }
    }
}
