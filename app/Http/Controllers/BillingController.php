<?php
namespace App\Http\Controllers;
use App\Http\Requests\GenerateBillingRequest;
use App\Models\{RentalBill, StallAssignment};
use App\Services\{BillingService, Ledger};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class BillingController extends Controller
{
    public function index(Request $r)
    {
        $this->authorize("billing.view");
        $q = RentalBill::with("tenant", "stall");
        $filters = $r->validate([
            'tenant_id'=>'nullable|integer', 'period'=>'nullable|date_format:Y-m', 'period_start'=>'nullable|date_format:Y-m-d',
        ]);
        $tenantId = $filters['tenant_id'] ?? null;
        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }
        if (!empty($filters['period_start'])) {
            $q->where('period', $filters['period_start']);
        } elseif (!empty($filters['period'])) {
            $q->where('period', 'like', $filters['period'].'%');
        }
        if ($r->filled("status")) {
            $q->where("status", $r->status);
        }
        return view('billing.index', [
            'bills' => $q->latest()->paginate(20)->withQueryString(),
            'tenantId' => $tenantId,
            'showFixedFilter' => config('billing.fixed_day_enabled') || $r->filled('period_start')
                || RentalBill::whereRaw('LENGTH(period) = 10')->exists(),
        ]);
    }
    public function create(Request $r)
    {
        $this->authorize("billing.generate");
        $assignments = StallAssignment::with('stall', 'tenant', 'agreement.assessment')->where('status', 'active')->get();
        [$fixed, $monthly] = $assignments->partition(function (StallAssignment $assignment) {
            return optional(optional($assignment->agreement)->assessment)->isFixedDays();
        });
        $showFixed = config('billing.fixed_day_enabled') || $fixed->isNotEmpty();
        $mode = $showFixed && $r->query('billing_mode') === 'fixed_days' ? 'fixed_days' : 'monthly';

        return view("billing.create", [
            'assignments' => $mode === 'fixed_days' ? $fixed : $monthly,
            'mode' => $mode,
            'showFixed' => $showFixed,
        ]);
    }
    public function generate(GenerateBillingRequest $r, BillingService $s)
    {
        $d = $r->validated();
        $field = ($d['billing_mode'] ?? null) === 'fixed_days' ? 'period_start' : 'period';
        try {
            $count = DB::transaction(function () use ($r, $s, $d, $field) {
                $assignments = StallAssignment::whereIn('id', $d['assignment_ids'])->orderBy('tenant_id')->orderBy('id')->get();
                foreach ($assignments as $assignment) {
                    $s->generate($r->user(), $assignment, $d[$field], $d['due_date']);
                }
                return $assignments->count();
            });
        } catch (ValidationException $error) {
            $messages = $error->errors();
            if ($field === 'period_start' && isset($messages['period'])) {
                $messages['period_start'] = $messages['period'];
                unset($messages['period']);
                throw ValidationException::withMessages($messages);
            }
            throw $error;
        }
        return redirect()
            ->route('billing.index', [$field => $d[$field]])
            ->with("success", $count . " draft bill(s) ready for review. Nothing was posted.");
    }
    public function show(RentalBill $bill)
    {
        $this->authorize("view", $bill);
        return view("billing.show", [
            "bill" => $bill->load("tenant", "stall", "assessment", "items", "adjustments", "allocations.payment"),
        ]);
    }
    public function post(Request $r, RentalBill $bill, BillingService $s)
    {
        $s->post($r->user(), $bill);
        return back()->with("success", "Bill posted.");
    }
    public function adjust(Request $r, RentalBill $bill, BillingService $s)
    {
        $s->adjust(
            $r->user(),
            $bill,
            $r->only(["amount", "reason", "approval_reference", "idempotency_key"]),
        );
        return back()->with("success", "Adjustment appended to the bill.");
    }
    public function receivables(Request $r)
    {
        $this->authorize("billing.view");
        $q = Ledger::bills()
            ->with("tenant", "stall")
            ->whereRaw(Ledger::balanceSql() . ">0");
        if ($r->filled("tenant_id")) {
            $q->where("tenant_id", $r->tenant_id);
        }
        if ($r->boolean("overdue")) {
            $q->where("due_date", "<", today()->toDateString());
        }
        return view("billing.receivables", [
            "bills" => $q->orderBy("due_date")->paginate(20)->withQueryString(),
            "total" => Ledger::sum($q, Ledger::balanceSql()),
        ]);
    }
}
