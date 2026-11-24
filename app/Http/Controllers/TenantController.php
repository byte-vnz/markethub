<?php
namespace App\Http\Controllers;

use App\Models\{AuditLog, Business, BusinessCategory, Market, MarketSection, Stall, StallCategory, Tenant};
use App\Services\{Ledger, RentalCalculationService, TenantDataService};
use App\Support\AssignmentRental;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TenantController extends Controller
{
    private const LEASE_STATUSES = [
        'draft'=>'Draft','ready'=>'Ready (all generated)',
        'generated'=>'Generated, awaiting review','approved'=>'Generated, review approved',
        'issued'=>'Issued','signed'=>'Signed','active'=>'Active','expired'=>'Expired',
        'terminated'=>'Terminated','void'=>'Void',
    ];
    private const TENANT_STATUSES = ['draft','active','inactive','archived'];

    public function index(Request $request)
    {
        $this->authorize('tenants.view');
        $actor = $request->user();
        if ($request->filled('lease_status')) {
            $this->authorize('contracts.view');
        }
        if ($request->filled('payment_status')) {
            $this->authorize('billing.view');
        }
        $filters = $request->validate([
            'q'=>'nullable|string|max:100','stall'=>'nullable|string|max:40',
            'category_id'=>'nullable|integer|exists:stall_categories,id',
            'business_category_id'=>'nullable|integer|exists:business_categories,id',
            'market_id'=>'nullable|integer|exists:markets,id',
            'section_id'=>'nullable|integer|exists:market_sections,id',
            'location_type'=>'nullable|in:inner,outer,unclassified',
            'status'=>['nullable', Rule::in(self::TENANT_STATUSES)],
            'lease_status'=>['nullable', Rule::in(array_keys(self::LEASE_STATUSES))],
            'payment_status'=>'nullable|in:overdue,outstanding,settled,unbilled',
        ]);
        $query = Tenant::query()->select('tenants.*')->with('businesses.businessCategory')
            ->with(['assignments'=>function ($query) use ($actor) {
                $query->where('status', 'active')->orderBy('id')->with('stall.market');
                if ($actor->can('billing.view')) {
                    $query->with('agreement.assessment');
                }
            }]);
        if ($actor->can('contracts.view')) {
            $query->with('contracts');
        }
        if ($actor->can('billing.view')) {
            $posted = Ledger::bills()->whereColumn('rental_bills.tenant_id', 'tenants.id');
            $query->withCount(['bills as posted_bill_count'=>function ($query) { $query->where('status', 'posted'); }]);
            $query->addSelect([
                'outstanding_balance'=>(clone $posted)->selectRaw('COALESCE(SUM('.Ledger::balanceSql().'), 0)'),
                'overdue_balance'=>(clone $posted)->where('due_date', '<', today()->toDateString())->selectRaw('COALESCE(SUM('.Ledger::balanceSql().'), 0)'),
            ]);
        }
        if ($actor->can('payments.view')) {
            $query->addSelect(['latest_payment_date'=>\App\Models\Payment::effective()->whereColumn('payments.tenant_id','tenants.id')
                ->orderByDesc('payment_date')->orderByDesc('id')->select('payment_date')->limit(1)]);
        }
        if ($request->filled('q')) {
            $search = '%'.$filters['q'].'%';
            $query->where(function ($query) use ($search) {
                $query->where('name','like',$search)->orWhere('reference','like',$search)
                    ->orWhere('present_account_number','like',$search)->orWhere('previous_account_number','like',$search)->orWhere('phone','like',$search)
                    ->orWhereHas('businesses', function ($businesses) use ($search) {
                        $businesses->where('name','like',$search)->orWhere('line_of_business','like',$search);
                    })->orWhereHas('assignments.stall', function ($stalls) use ($search) { $stalls->where('code','like',$search); });
            });
        }
        if ($request->anyFilled(['stall','category_id','market_id','section_id','location_type'])) {
            $query->whereHas('assignments.stall', function ($stalls) use ($filters) {
                foreach (['category_id'=>'stall_category_id','market_id'=>'market_id','section_id'=>'market_section_id','location_type'=>'location_type'] as $filter=>$field) {
                    if (!empty($filters[$filter])) {
                        $filters[$filter] === 'unclassified' ? $stalls->whereNull($field) : $stalls->where($field, $filters[$filter]);
                    }
                }
                if (!empty($filters['stall'])) {
                    $stalls->where('code', 'like', '%'.$filters['stall'].'%');
                }
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $filters['status']);
        }
        if ($request->filled('lease_status')) {
            $query->whereHas('contracts', function ($contracts) use ($filters) {
                $status = $filters['lease_status'];
                $contracts->where('status', in_array($status, ['generated','approved'], true) ? 'ready' : $status);
                if ($status === 'generated') {
                    $contracts->whereNull('approved_at');
                } elseif ($status === 'approved') {
                    $contracts->whereNotNull('approved_at');
                }
            });
        }
        if ($request->filled('business_category_id')) {
            $query->whereHas('businesses', function ($businesses) use ($filters) { $businesses->where('business_category_id', $filters['business_category_id']); });
        }
        if ($request->filled('payment_status')) {
            $this->filterPayments($query, $filters['payment_status']);
        }
        return response()->view('tenants.index', [
            'records'=>$query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'categories'=>StallCategory::orderBy('name')->get(),
            'businessCategories'=>BusinessCategory::orderBy('name')->get(),
            'markets'=>Market::orderBy('name')->get(),
            'sections'=>MarketSection::inDisplayOrder()->get(),
            'tenantStatuses'=>self::TENANT_STATUSES, 'leaseStatuses'=>self::LEASE_STATUSES,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function filterPayments($query, string $status): void
    {
        $posted = Ledger::bills()->whereColumn('rental_bills.tenant_id','tenants.id')->selectRaw('1');
        $outstanding = (clone $posted)->whereRaw(Ledger::balanceSql().' > 0');
        if ($status === 'overdue') {
            $query->whereExists($outstanding->where('due_date', '<', today()->toDateString())->toBase());
        } elseif ($status === 'outstanding') {
            $query->whereExists($outstanding->toBase());
        } elseif ($status === 'settled') {
            $query->whereExists($posted->toBase())->whereNotExists($outstanding->toBase());
        } else {
            $query->whereNotExists($posted->toBase());
        }
    }

    public function create()
    {
        $this->authorize('tenants.manage');
        return $this->form(new Tenant(['status'=>'draft','citizenship'=>'Filipino']), collect(), null, collect());
    }

    public function edit(Request $request, $id)
    {
        $record = Tenant::findOrFail($id);
        $this->authorize('update', $record);
        if (optional($request->session()->get('errors'))->has('registration_version')) {
            $request->session()->forget('_old_input');
        }
        $assignments = $record->assignments()->with('stall')->orderByDesc('start_date')->get();
        $selected = old('helper_assignment_id', $request->input('assignment_id'));
        $helperAssignment = $selected ? $assignments->firstWhere('id', (int)$selected) : $assignments->first();
        $helpers = $helperAssignment ? $record->helpers()->where('stall_assignment_id',$helperAssignment->id)->orderBy('slot')->get()->keyBy('slot') : collect();
        return $this->form($record, $assignments, $helperAssignment, $helpers);
    }

    private function form(Tenant $record, $assignments, $helperAssignment, $helpers)
    {
        $registration = !$record->exists || $record->status === 'draft';
        $data = compact('record','assignments','helperAssignment','helpers','registration');
        if ($registration) {
            $draft = $record->registration_draft ?? [];
            $keys = array_flip($draft['businesses'] ?? []);
            $businesses = [];
            foreach ($record->businesses()->orderBy('id')->get() as $business) {
                $key = $keys[$business->id] ?? 'business-'.$business->id;
                $businesses[$key] = $business->getAttributes();
                $keys[$business->id] = $key;
            }
            $pending = [];
            foreach ($draft['assignments'] ?? [] as $selection) {
                $pending[] = $selection + ['business_key'=>$keys[$selection['business_id']] ?? ''];
            }
            $retry = session()->hasOldInput('registration_action');
            $businesses = $retry ? $this->retryRows(old('businesses', [])) : $businesses;
            $pending = $retry ? $this->retryRows(old('pending_assignments', [])) : $pending;
            $selectedIds = collect($pending)->pluck('stall_id')->filter(function ($id) { return is_scalar($id) && ctype_digit((string)$id); });
            $stalls = collect();
            if (auth()->user()->can('stalls.view') || auth()->user()->can('assignments.manage')) {
                $stalls = $this->eligibleStalls()->orWhereIn('id', $selectedIds)->with('market','section')->orderBy('market_id')->orderBy('code')->get();
            }
            $businessCategories = BusinessCategory::orderBy('sort_order')->orderBy('name')->get();
            $data += compact('businesses','pending','stalls','businessCategories','draft');
        }
        return response()->view('tenants.form', $data)->header('Cache-Control', 'private, no-store');
    }

    private function retryRows($rows): array
    {
        $rows = array_slice(array_filter((array)$rows, function ($row, $key) {
            return is_array($row) && preg_match('/\A[a-zA-Z0-9_-]{1,40}\z/', (string)$key);
        }, ARRAY_FILTER_USE_BOTH), 0, 10, true);
        $scalarFields = function ($value) { return is_scalar($value) || $value === null; };
        foreach ($rows as &$row) {
            $helpers = array_slice((array)($row['helpers'] ?? []), 0, 2, true);
            $row = array_filter($row, $scalarFields);
            $row['helpers'] = array_map(function ($helper) use ($scalarFields) {
                return array_filter((array)$helper, $scalarFields);
            }, $helpers);
        }
        return $rows;
    }

    private function eligibleStalls()
    {
        return Stall::query()->whereIn('status', ['vacant','reserved'])->whereIn('location_type', ['inner','outer'])
            ->where('area', '>', 0)->whereHas('market', function ($market) { $market->where('status','active'); })
            ->inActiveSection();
    }

    public function save($request, $id = null)
    {
        $service = app(TenantDataService::class);
        $tenant = $service->save($request->user(), $request->validated(), $id);
        $response = redirect()->route('records.show', ['tenants',$tenant->id]);
        if (($request->input('registration_action') || !$id) && $service->hasDuplicate($tenant)) {
            $response->with('duplicate_warning', 'Another tenant has a similar name, account number or phone. Review the tenant list before making any record corrections.');
        }
        if ($request->input('registration_action') === 'confirm' && $request->user()->can('rentals.override.request') &&
            $request->user()->can('rentals.override.view') && $request->user()->can('billing.view') && $tenant->assignments()->exists()) {
            $response->with('registered_rental_link', true);
        }
        return $response->with('success', $tenant->status === 'draft' ? 'Registration draft saved.' : 'Tenant saved.');
    }

    public function preview(Request $request, RentalCalculationService $calculator)
    {
        $this->authorize('tenants.manage');
        $this->authorize('assignments.manage');
        $data = $request->validate([
            'tenant_id'=>'nullable|integer|exists:tenants,id','business_id'=>'nullable|integer|required_without:business_category_id',
            'business_category_id'=>'nullable|integer|required_without:business_id|exists:business_categories,id',
            'stall_id'=>'required|integer','start_date'=>'required|date_format:Y-m-d|before_or_equal:today',
            'end_date'=>'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        $tenant = !empty($data['tenant_id']) ? Tenant::findOrFail($data['tenant_id']) : null;
        if ($tenant) {
            $this->authorize('update', $tenant);
            if (!in_array($tenant->status, ['active','draft'], true)) {
                throw ValidationException::withMessages(['tenant_id'=>'Choose an active tenant or an open registration draft.']);
            }
        }
        if (!empty($data['business_id'])) {
            $business = $tenant ? $tenant->businesses()->find($data['business_id']) : null;
            if (!$business || $business->status !== 'active') {
                throw ValidationException::withMessages(['business_id'=>'Choose an active business belonging to this tenant.']);
            }
            if (!empty($data['business_category_id'])) {
                $business->business_category_id = $data['business_category_id'];
            }
        } else {
            $business = new Business(['business_category_id'=>$data['business_category_id'],'status'=>'active']);
        }
        $business->setRelation('tenant', new Tenant(['status'=>'active']));
        $stall = $this->eligibleStalls()->whereKey($data['stall_id'])->first();
        if (!$stall || $stall->assignments()->where('start_date', '<=', $data['end_date'] ?? '9999-12-31')
            ->where(function ($query) use ($data) { $query->whereNull('end_date')->orWhere('end_date', '>=', $data['start_date']); })->exists()) {
            throw ValidationException::withMessages(['stall_id'=>'This stall is unavailable for the selected dates or lacks a verified location and area.']);
        }
        $period = substr($data['start_date'], 0, 7);
        $amounts = $calculator->resolve($business, $stall, $data['start_date'], $period);
        return response()->json(['period'=>$period] + Arr::only($amounts, [
            'market_name','stall_code','business_category_name','location_type','floor_area','minimum_rate','maximum_rate',
            'rental_rate_sqm','daily_rental','billing_days','billing_day_rule','daily_cusa','monthly_rental','monthly_cusa',
            'total_monthly_rental_cusa','advance_rental','security_deposit','total_initial_payment','advance_deposit_authorized',
        ]))->header('Cache-Control', 'private, no-store');
    }

    public function show($id)
    {
        $record = Tenant::findOrFail($id);
        $this->authorize('view', $record);
        $actor = auth()->user();
        $tabs = [
            'overview'=>'Overview','personal'=>'Personal Information','businesses'=>'Businesses',
            'assignments'=>'Stall Assignments','rental'=>'Rental Information','helpers'=>'Authorized Helpers',
            'payments'=>'Payment History','contracts'=>'Lease Contracts','activity'=>'Activity History',
        ];
        foreach (['assignments'=>'assignments.view','rental'=>'billing.view','helpers'=>'assignments.view',
            'payments'=>'payments.view','contracts'=>'contracts.view','activity'=>'audit.view'] as $tab=>$permission) {
            if (!$actor->can($permission)) {
                unset($tabs[$tab]);
            }
        }
        $tab = request()->query('tab', 'overview');
        abort_unless(is_string($tab), 404);
        abort_unless(isset($tabs[$tab]), 404);
        $data = compact('record','tabs','tab');
        if (in_array($tab, ['assignments','rental','helpers'], true)) {
            $data['profileAssignments'] = $record->assignments()->with('stall.market','stall.section','stall.category','business.businessCategory')
                ->when($actor->can('billing.view'), function ($query) { $query->with('agreement.assessment'); })
                ->when($tab === 'rental' && $actor->can('rentals.override.view') && $actor->can('rentals.override.history'), function ($query) {
                    $query->withCount(['rentalOverrides as pending_override_count'=>fn ($overrides) => $overrides->where('status', 'pending')]);
                })
                ->orderByDesc('start_date')->orderByDesc('id')->paginate(15)->withQueryString();
        }
        if ($tab === 'rental') {
            $data['period'] = request()->validate(['period'=>'nullable|date_format:Y-m'])['period'] ?? today()->format('Y-m');
            $data['rentalQuotes'] = [];
            $data['requestMonths'] = [];
            if ($actor->can('rentals.override.view')) {
                foreach ($data['profileAssignments'] as $assignment) {
                    $assignment->setRelation('tenant', $record);
                    $data['rentalQuotes'][$assignment->id] = AssignmentRental::quote($assignment, $data['period']);
                    if ($actor->can('rentals.override.request')) {
                        $data['requestMonths'][$assignment->id] = AssignmentRental::earliestRequestMonth($assignment);
                    }
                }
            }
        }
        if ($tab === 'helpers') {
            $record->load('helpers');
        }
        if ($tab === 'businesses') {
            $data['profileBusinesses'] = $record->businesses()->with('businessCategory')->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
        }
        if ($tab === 'overview') {
            $data['businessCount'] = $record->businesses()->where('status','active')->count();
            $data['assignmentCount'] = $actor->can('assignments.view') ? $record->assignments()->where('status','active')->count() : null;
            $data['lastPayment'] = $actor->can('payments.view') ? $record->payments()->effective()->latest('payment_date')->latest('id')->first() : null;
        }
        if (in_array($tab, ['overview','rental'], true) && $actor->can('billing.view')) {
            $data['balance'] = Ledger::sum(Ledger::bills()->where('tenant_id', $record->id), Ledger::balanceSql());
        }
        if ($tab === 'payments') {
            $data['profilePayments'] = $record->payments()->with('reversal')->latest('payment_date')->latest('id')->paginate(15)->withQueryString();
        }
        if ($tab === 'contracts') {
            $data['contracts'] = $record->contracts()->with('assignment.stall')->latest('id')->paginate(15)->withQueryString();
        }
        if ($tab === 'activity') {
            $entities = ['Business'=>$record->businesses()];
            foreach (['StallAssignment'=>['assignments.view','assignments'],'LeaseContract'=>['contracts.view','contracts'],
                'RentalBill'=>['billing.view','bills'],'Payment'=>['payments.view','payments']] as $type=>[$permission,$relation]) {
                if ($actor->can($permission)) {
                    $entities[$type] = $record->$relation();
                }
            }
            $data['activity'] = AuditLog::with('actor:id,name')->where(function ($query) use ($record, $entities) {
                $query->where(function ($events) use ($record) { $events->where('entity_type','Tenant')->where('entity_id',$record->id); });
                foreach ($entities as $type=>$relation) {
                    $query->orWhere(function ($events) use ($type, $relation) {
                        $events->where('entity_type',$type)->whereIn('entity_id',$relation->select('id')->getQuery());
                    });
                }
            })->latest('id')->paginate(20)->withQueryString();
        }
        return response()->view('tenants.show', $data)->header('Cache-Control','private, no-store');
    }
}
