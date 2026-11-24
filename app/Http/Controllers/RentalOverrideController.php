<?php

namespace App\Http\Controllers;

use App\Http\Requests\RentalOverrideRequest;
use App\Models\{RentalOverride, StallAssignment, Tenant};
use App\Services\{RentalCalculationService, RentalOverrideService};
use App\Support\AssignmentRental;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RentalOverrideController extends Controller
{
    private $service;

    public function __construct(RentalOverrideService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, Tenant $tenant, StallAssignment $assignment)
    {
        $this->scope($tenant, $assignment, 'history');
        $period = $this->period($request);
        $rows = $assignment->rentalOverrides()->with(['requester:id,name', 'approver:id,name', 'initialAssessment'])
            ->latest('revision')->paginate(10)->withQueryString();
        $user = $request->user();
        $review = [];
        $fingerprints = [];
        foreach ($rows as $row) {
            $row->setRelation('assignment', $assignment);
            if ($row->status === 'pending' && $user->can('rentals.override.approve') && (int) $row->requested_by !== (int) $user->id) {
                $proposal = app(RentalCalculationService::class)->proposedAmounts(
                    $row->initialAssessment, substr($row->effective_from, 0, 7), $row->override_type,
                    $row->override_rate_per_sqm, $row->override_daily_amount
                );
                $review[$row->id] = RentalOverrideService::review($row) + Arr::only($proposal, ['out_of_range', 'zero_rent']);
            }
            if ($row->status === 'draft' && $user->can('rentals.override.request') && (int) $row->requested_by === (int) $user->id) {
                $fingerprints[$row->id] = RentalOverrideService::fingerprint($row);
            }
        }
        [$quote, $quoteError] = AssignmentRental::quote($assignment, $period);
        $earliest = $user->can('rentals.override.request') ? AssignmentRental::earliestRequestMonth($assignment) : null;
        return $this->privateView('rental-overrides.index', compact('tenant', 'assignment', 'rows', 'period', 'quote', 'quoteError', 'earliest', 'review', 'fingerprints'));
    }

    public function create(Request $request, Tenant $tenant, StallAssignment $assignment)
    {
        $this->scope($tenant, $assignment, 'request');
        $period = $this->period($request);
        $input = $request->validate([
            'source_override_id'=>'nullable|integer|min:1', 'computation_mode'=>'nullable|in:automatic,manual_override',
        ]);
        if (isset($input['source_override_id'])) {
            $this->authorize('rentals.override.history');
        }
        $earliest = AssignmentRental::earliestRequestMonth($assignment);
        if (($input['computation_mode'] ?? null) === 'automatic') {
            if ($earliest && $request->user()->can('rentals.override.history') &&
                $assignment->rentalOverrides()->covering($earliest, $assignment->end_date)->exists()) {
                return $this->historyRedirect($request, $tenant, $assignment)->with('rental_mode_notice',
                    'An authorized reviewer can choose Return to automatic on the approved request and set a prospective effective date.');
            }
            return $this->profileRedirect($request, $tenant)->with('rental_mode_notice',
                'Automatic rent applies by default. An approved override can end through an authorized return to automatic or its recorded expiry.');
        }
        if (!$earliest) {
            return $this->historyRedirect($request, $tenant, $assignment)->withErrors(['assignment'=>'Choose an active assessed assignment with a full prospective month.']);
        }
        $source = isset($input['source_override_id']) ? RentalOverride::where('stall_assignment_id', $assignment->id)
            ->where('status', '!=', 'draft')->findOrFail($input['source_override_id']) : null;
        $draft = new RentalOverride(['submission_key'=>(string) Str::uuid(), 'source_override_id'=>optional($source)->id,
            'override_type'=>optional($source)->override_type ?? 'rate_per_sqm']);
        return $this->form($tenant, $assignment, $draft, $earliest, $period);
    }

    public function edit(Request $request, Tenant $tenant, StallAssignment $assignment, RentalOverride $override)
    {
        $this->scope($tenant, $assignment, 'request');
        abort_unless((int) $override->stall_assignment_id === (int) $assignment->id && $override->status === 'draft', 404);
        abort_unless((int) $override->requested_by === (int) $request->user()->id, 403);
        return $this->form($tenant, $assignment, $override, AssignmentRental::earliestRequestMonth($assignment), $this->period($request));
    }

    public function store(RentalOverrideRequest $request, Tenant $tenant, StallAssignment $assignment)
    {
        $data = $request->validated();
        if (isset($data['source_override_id'])) {
            $this->authorize('rentals.override.history');
        }
        $row = DB::transaction(function () use ($request, $assignment, $data) {
            $row = $this->service->saveDraft($request->user(), $assignment, $data);
            return ($data['intent'] ?? null) === 'submit' ? $this->service->submit($request->user(), $row) : $row;
        }, 3);
        if ($row->status === 'draft') {
            return redirect()->route('rental-overrides.edit', ['tenant'=>$tenant, 'assignment'=>$assignment, 'override'=>$row, 'period'=>$request->input('period')])
                ->with('success', 'Draft saved.');
        }
        return $this->historyRedirect($request, $tenant, $assignment)->with('success', 'Request submitted for review.');
    }

    public function update(RentalOverrideRequest $request, Tenant $tenant, StallAssignment $assignment, RentalOverride $override)
    {
        $this->service->saveDraft($request->user(), $assignment, $request->validated(), $override);
        return redirect()->route('rental-overrides.edit', ['tenant'=>$tenant, 'assignment'=>$assignment, 'override'=>$override,
            'period'=>$request->input('period')])->with('success', 'Draft saved.');
    }

    public function preview(RentalOverrideRequest $request, Tenant $tenant, StallAssignment $assignment)
    {
        $amounts = $this->service->preview($request->user(), $assignment, $request->validated());
        return response()->json(Arr::only($amounts, [
            'computation_mode', 'rental_override_id', 'automatic_rate_per_sqm', 'automatic_daily_rental', 'daily_rental',
            'rental_difference', 'monthly_rental', 'monthly_cusa', 'total_monthly_rental_cusa', 'billing_days',
            'equivalent_rate_per_sqm', 'contract_rate_consistent', 'out_of_range', 'zero_rent',
        ]))->header('Cache-Control', 'private, no-store');
    }

    public function submit(RentalOverrideRequest $request, Tenant $tenant, StallAssignment $assignment, RentalOverride $override)
    {
        abort_unless((int) $override->requested_by === (int) $request->user()->id, 403);
        $matchingTerms = hash_equals($override->request_hash, $request->input('draft_terms_hash'));
        if (in_array($override->status, ['draft', 'pending'], true) && (!$matchingTerms ||
            ($override->status === 'draft' && !hash_equals(RentalOverrideService::fingerprint($override), $request->input('draft_fingerprint'))))) {
            throw ValidationException::withMessages(['draft_fingerprint'=>'Review the latest saved draft before submitting.']);
        }
        $this->service->submit($request->user(), $override);
        return $this->historyRedirect($request, $tenant, $assignment)->with('success', 'Request submitted for review.');
    }

    public function approve(RentalOverrideRequest $request, Tenant $tenant, StallAssignment $assignment, RentalOverride $override)
    {
        $this->service->approve($request->user(), $override, $request->input('fingerprint'));
        return $this->historyRedirect($request, $tenant, $assignment)->with('success', 'Override approved for the recorded effective period.');
    }

    public function reject(RentalOverrideRequest $request, Tenant $tenant, StallAssignment $assignment, RentalOverride $override)
    {
        $this->service->reject($request->user(), $override, $request->input('reason'));
        return $this->historyRedirect($request, $tenant, $assignment)->with('success', 'Request rejected. Applicable rent is unchanged.');
    }

    public function revoke(RentalOverrideRequest $request, Tenant $tenant, StallAssignment $assignment, RentalOverride $override)
    {
        $this->service->revoke($request->user(), $override, $request->validated());
        return $this->historyRedirect($request, $tenant, $assignment)->with('success', 'Return to automatic recorded for the selected effective date.');
    }

    private function scope(Tenant $tenant, StallAssignment $assignment, string $action): void
    {
        $this->authorize('view', $tenant);
        $this->authorize('rentals.override.view');
        $this->authorize('rentals.override.'.$action);
        abort_unless((int) $assignment->tenant_id === (int) $tenant->id, 404);
        $assignment->setRelation('tenant', $tenant);
        $assignment->loadMissing(['business', 'stall.section', 'agreement.assessment']);
    }

    private function form(Tenant $tenant, StallAssignment $assignment, RentalOverride $draft, ?string $earliest, string $period)
    {
        $assessment = AssignmentRental::initial($assignment);
        $fingerprint = $draft->exists ? RentalOverrideService::fingerprint($draft) : null;
        $postedFingerprint = old('draft_fingerprint');
        $recoverInput = !$draft->exists || (is_string($postedFingerprint) && hash_equals($fingerprint, $postedFingerprint));
        $end = $assignment->end_date ? Carbon::parse($assignment->end_date) : null;
        $lastFullMonth = $end && $end->day !== $end->daysInMonth
            ? $end->subMonthNoOverflow()->endOfMonth()->toDateString() : $assignment->end_date;
        $selectedStart = $period.'-01';
        $defaultStart = $earliest && $selectedStart >= $earliest && (!$lastFullMonth || $selectedStart <= $lastFullMonth)
            ? $selectedStart : $earliest;
        return $this->privateView('rental-overrides.form', compact('tenant', 'assignment', 'draft', 'earliest', 'assessment',
            'period', 'fingerprint', 'lastFullMonth', 'defaultStart', 'recoverInput'));
    }

    private function period(Request $request): string
    {
        return $request->validate(['period'=>'nullable|date_format:Y-m'])['period'] ?? today()->format('Y-m');
    }

    private function historyRedirect(Request $request, Tenant $tenant, StallAssignment $assignment)
    {
        $period = $request->input('period');
        return $request->user()->can('rentals.override.history')
            ? redirect()->route('rental-overrides.index', compact('tenant', 'assignment', 'period'))
            : $this->profileRedirect($request, $tenant);
    }

    private function profileRedirect(Request $request, Tenant $tenant)
    {
        return redirect()->route('records.show', ['tenants', $tenant->id, 'tab'=>$request->user()->can('billing.view') ? 'rental' : 'overview',
            'period'=>$request->input('period')]);
    }

    private function privateView(string $view, array $data)
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store');
    }
}
