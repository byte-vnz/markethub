<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessCategoryRequest;
use App\Models\{BusinessCategory, BusinessCategoryRentalRate, Market, RentalPolicy, StallAssignment};
use App\Services\BusinessCategoryService;
use Illuminate\Http\Request;

class BusinessCategoryController extends Controller
{
    private $service;

    public function __construct(BusinessCategoryService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->authorizeView($request);
        $today = today()->toDateString();
        $categories = BusinessCategory::withCount('businesses')->addSelect([
            'assigned_stalls_count' => StallAssignment::query()->selectRaw('COUNT(*)')
                ->join('businesses', 'businesses.id', '=', 'stall_assignments.business_id')
                ->whereColumn('businesses.business_category_id', 'business_categories.id')
                ->whereNotNull('stall_assignments.active_stall_id'),
        ])->with(['rentalRates' => function ($query) use ($today) {
            $query->where(function ($revisions) use ($today) {
                $revisions->where(function ($current) use ($today) {
                    $current->whereIn('status', ['approved', 'retired'])->whereNotNull('approved_at')
                        ->whereDate('effective_from', '<=', $today)
                        ->where(function ($end) use ($today) {
                            $end->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today);
                        });
                })->orWhere('status', 'draft');
            })->latest('id');
        }])->orderBy('sort_order')->orderBy('name')->paginate(25);

        return view('business-categories.index', compact('categories'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('rates.manage'), 403);
        return view('business-categories.edit', ['category' => new BusinessCategory]);
    }

    public function store(BusinessCategoryRequest $request)
    {
        $category = $this->service->saveCategory($request->user(), $request->validated());
        return redirect()->route('business-categories.edit', $category)->with('success', 'Business category created.');
    }

    public function edit(Request $request, BusinessCategory $category)
    {
        $this->authorizeView($request);
        $rates = $category->rentalRates()->with('approver:id,name')->latest('id')->paginate(15);
        return view('business-categories.edit', compact('category', 'rates'));
    }

    public function update(BusinessCategoryRequest $request, BusinessCategory $category)
    {
        $this->service->saveCategory($request->user(), $request->validated(), $category);
        return back()->with('success', 'Business category updated.');
    }

    public function storeRate(BusinessCategoryRequest $request, BusinessCategory $category)
    {
        $this->service->saveSchedule($request->user(), new BusinessCategoryRentalRate(['business_category_id' => $category->id]), $request->validated());
        return back()->with('success', 'Draft rental rate saved.');
    }

    public function updateRate(BusinessCategoryRequest $request, BusinessCategory $category, BusinessCategoryRentalRate $rate)
    {
        $this->assertRateCategory($category, $rate);
        $this->service->saveSchedule($request->user(), $rate, $request->validated());
        return back()->with('success', 'Draft rental rate updated.');
    }

    public function approveRate(BusinessCategoryRequest $request, BusinessCategory $category, BusinessCategoryRentalRate $rate)
    {
        $this->assertRateCategory($category, $rate);
        $this->service->approve($request->user(), $rate, $request->validated()['draft_fingerprint']);
        return back()->with('success', 'Rental rate approved.');
    }

    public function retireRate(BusinessCategoryRequest $request, BusinessCategory $category, BusinessCategoryRentalRate $rate)
    {
        $this->assertRateCategory($category, $rate);
        $this->service->retire($request->user(), $rate, $request->validated());
        return back()->with('success', 'Rental rate retired.');
    }

    public function policies(Request $request)
    {
        $this->authorizeView($request);
        $filter = $request->validate(['market_id' => 'nullable|integer|exists:markets,id']);
        $marketId = $filter['market_id'] ?? null;
        $markets = Market::orderBy('name')->get(['id', 'name']);
        $policies = RentalPolicy::with('market:id,name', 'approver:id,name')
            ->when($marketId, function ($query) use ($marketId) { $query->where('market_id', $marketId); })
            ->latest('id')->paginate(15)->withQueryString();
        return view('business-categories.policies', compact('markets', 'marketId', 'policies'));
    }

    public function storePolicy(BusinessCategoryRequest $request)
    {
        $data = $request->validated();
        $this->service->saveSchedule($request->user(), new RentalPolicy(['market_id' => $data['market_id']]), $data);
        return back()->with('success', 'Draft rental policy saved.');
    }

    public function updatePolicy(BusinessCategoryRequest $request, RentalPolicy $policy)
    {
        $this->service->saveSchedule($request->user(), $policy, $request->validated());
        return back()->with('success', 'Draft rental policy updated.');
    }

    public function approvePolicy(BusinessCategoryRequest $request, RentalPolicy $policy)
    {
        $this->service->approve($request->user(), $policy, $request->validated()['draft_fingerprint']);
        return back()->with('success', 'Rental policy approved.');
    }

    public function retirePolicy(BusinessCategoryRequest $request, RentalPolicy $policy)
    {
        $this->service->retire($request->user(), $policy, $request->validated());
        return back()->with('success', 'Rental policy retired.');
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->can('rates.view'), 403);
    }

    private function assertRateCategory(BusinessCategory $category, BusinessCategoryRentalRate $rate): void
    {
        abort_unless((int) $rate->business_category_id === (int) $category->id, 404);
    }
}
