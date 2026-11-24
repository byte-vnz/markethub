<?php
namespace App\Http\Controllers;

use App\Http\Requests\BusinessRequest;
use App\Models\{Business, BusinessCategory, Tenant};
use App\Services\BusinessService;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('tenants.view');
        $filters = $request->validate([
            'q'=>'nullable|string|max:100','orphaned'=>'nullable|boolean',
            'status'=>'nullable|in:active,inactive,archived','tenant_id'=>'nullable|integer|exists:tenants,id',
        ]);
        $query = Business::with('tenant', 'businessCategory');
        if ($request->boolean('orphaned')) {
            $this->authorize('tenants.manage');
            $query->doesntHave('tenant');
        } elseif (!$request->user()->can('tenants.manage')) {
            $query->has('tenant');
        }
        if ($request->filled('q')) {
            $search = '%'.$filters['q'].'%';
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', $search)->orWhere('line_of_business', 'like', $search)
                    ->orWhereHas('tenant', function ($tenant) use ($search) { $tenant->where('name', 'like', $search); });
            });
        }
        foreach (['status','tenant_id'] as $key) {
            if ($request->filled($key)) {
                $query->where($key, $filters[$key]);
            }
        }
        return response()->view('tenants.business-index', ['businesses'=>$query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function create(Tenant $tenant)
    {
        $this->authorize('update', $tenant);
        return $this->form($tenant, new Business(['status'=>'active']));
    }

    public function store(BusinessRequest $request, Tenant $tenant, BusinessService $service)
    {
        $this->authorize('update', $tenant);
        $service->save($request->user(), $request->validated(), null, $tenant->id);
        return $this->profile($tenant->id, 'Business registered.');
    }

    public function edit(Tenant $tenant, Business $business)
    {
        $this->authorize('update', $tenant);
        $this->owns($tenant, $business);
        return $this->form($tenant, $business);
    }

    public function update(BusinessRequest $request, Tenant $tenant, Business $business, BusinessService $service)
    {
        $this->authorize('update', $tenant);
        $this->owns($tenant, $business);
        $updated = $service->save($request->user(), $request->validated(), $business->id, $tenant->id);
        $message = $updated->business_category_id != $business->business_category_id
            ? 'Business updated. Existing assessments and contracts retain their recorded category. Use an authorized renewal to apply the new category to the rental terms.'
            : 'Business updated.';
        return $this->profile($tenant->id, $message);
    }

    public function archive(Request $request, Tenant $tenant, Business $business, BusinessService $service)
    {
        $this->authorize('update', $tenant);
        $this->owns($tenant, $business);
        $service->save($request->user(), ['status'=>'archived'], $business->id, $tenant->id);
        return $this->profile($tenant->id, 'Business archived. Its history is retained.');
    }

    public function reconcile(Request $request, Business $business, BusinessService $service)
    {
        $this->authorize('tenants.manage');
        $data = $request->validate(['tenant_reference'=>'required|string|max:60','reason'=>'required|string|max:2000']);
        $tenant = Tenant::where('reference', $data['tenant_reference'])->first();
        if (!$tenant) {
            throw \Illuminate\Validation\ValidationException::withMessages(['tenant_reference'=>'Choose an existing tenant by its exact reference.']);
        }
        $service->reconcile($request->user(), $business, $tenant, $data['reason']);
        return $this->profile($tenant->id, 'Business linked to the selected tenant.');
    }

    private function form(Tenant $tenant, Business $business)
    {
        $businessCategories = BusinessCategory::orderBy('sort_order')->orderBy('name')->get();
        return response()->view('tenants.business-form', compact('tenant', 'business', 'businessCategories'))->header('Cache-Control', 'private, no-store');
    }

    private function owns(Tenant $tenant, Business $business): void
    {
        abort_unless((int)$business->tenant_id === (int)$tenant->id, 404);
    }

    private function profile($tenantId, string $message)
    {
        return redirect()->route('records.show', ['type'=>'tenants','id'=>$tenantId,'tab'=>'businesses'])->with('success', $message);
    }
}
