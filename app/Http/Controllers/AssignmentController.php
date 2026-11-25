<?php
namespace App\Http\Controllers;
use App\Models\{StallAssignment, Stall, Tenant, Business};
use App\Services\AssignmentService;
use App\Http\Requests\AssignmentRequest;
use Illuminate\Http\Request;
class AssignmentController extends Controller
{
    public function index(Request $r)
    {
        $this->authorize("assignments.view");
        $q = StallAssignment::with("stall", "tenant", "business", "agreement.assessment");
        if ($r->filled("tenant_id")) {
            $q->where("tenant_id", $r->tenant_id);
        }
        if ($r->filled("stall_id")) {
            $q->where("stall_id", $r->stall_id);
        }
        return view("assignments.index", [
            "assignments" => $q->latest()->paginate(20)->withQueryString(),
        ]);
    }
    public function create()
    {
        $this->authorize("assignments.manage");
        return view("assignments.form", [
            "stalls" => Stall::whereIn("status", ["vacant", "reserved"])
                ->inActiveSection()
                ->whereNotNull("location_type")
                ->with("market")
                ->orderBy("code")
                ->get(),
            "tenants" => Tenant::where("status", "active")->orderBy("name")->get(),
            "businesses" => $this->businesses()->with("tenant")->get(),
        ]);
    }
    public function store(AssignmentRequest $r, AssignmentService $s)
    {
        $s->assign($r->user(), $r->validated());
        return redirect()
            ->route("assignments.index")
            ->with("success", "Tenant assigned. History and agreement saved.");
    }
    public function end(Request $r, StallAssignment $assignment, AssignmentService $s)
    {
        $s->end($r->user(), $assignment, $r->end_date, $r->reason);
        return back()->with("success", "Assignment ended. History retained.");
    }
    public function transferForm(StallAssignment $assignment)
    {
        $this->authorize("assignments.manage");
        $assessment = optional($assignment->agreement)->assessment;
        return view("assignments.transfer", [
            "assignment" => $assignment,
            "originalInterval" => optional($assessment)->isFixedDays() ? $assessment->billing_days : null,
            "stalls" => Stall::where(function ($query) use ($assignment) {
                $query->whereIn("status", ["vacant", "reserved"])
                    ->orWhere("id", $assignment->stall_id);
            })
                ->inActiveSection()
                ->whereNotNull("location_type")
                ->with("market")
                ->orderBy("code")
                ->get(),
            "businesses" => $this->businesses()->where("tenant_id", $assignment->tenant_id)->get(),
        ]);
    }
    public function transfer(AssignmentRequest $r, StallAssignment $assignment, AssignmentService $s)
    {
        $s->transfer(
            $r->user(),
            $assignment,
            $r->validated(),
        );
        return redirect()
            ->route("assignments.index")
            ->with("success", "Transfer / renewal saved with separate assignment history.");
    }

    private function businesses()
    {
        return Business::with("businessCategory")->where("status", "active")
            ->whereHas("tenant", function ($query) {
                $query->where("status", "active");
            })->whereHas("businessCategory", function ($query) {
                $query->where("is_active", true);
            })->orderBy("name");
    }
}
