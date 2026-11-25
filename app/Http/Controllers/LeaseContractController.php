<?php
namespace App\Http\Controllers;
use App\Http\Requests\LeaseContractRequest;
use App\Models\{LeaseContract, ContractTemplate, StallAssignment};
use App\Services\{LeaseContractService, ContractTemplateService, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage, DB};
use Illuminate\Support\Str;
class LeaseContractController extends Controller
{
    public function index(Request $r)
    {
        $this->authorize("contracts.view");
        $q = LeaseContract::with(["tenant", "assignment.stall", "template"]);
        if ($r->filled("tenant_id")) {
            $filter = $r->validate(["tenant_id" => "required|integer|exists:tenants,id"]);
            $q->where("tenant_id", $filter["tenant_id"]);
        }
        if ($r->filled("q")) {
            $search = substr($r->q, 0, 100);
            $q->where(function ($q) use ($search) {
                $q->where("control_number", "like", "%" . $search . "%")->orWhereHas(
                    "tenant",
                    function ($q) use ($search) {
                        $q->where("name", "like", "%" . $search . "%");
                    },
                );
            });
        }
        if ($r->filled("status")) {
            $q->where("status", $r->status);
        }
        return view("contracts.index", [
            "contracts" => $q->latest("id")->paginate(20)->withQueryString(),
        ]);
    }
    public function create(Request $r)
    {
        $this->authorize("contracts.create");
        $filters = $r->validate(['tenant_id'=>'nullable|integer|exists:tenants,id','assignment_id'=>'nullable|integer|exists:stall_assignments,id']);
        $tenantId = $filters['tenant_id'] ?? null;
        if (!empty($filters['assignment_id'])) {
            $selected = StallAssignment::findOrFail($filters['assignment_id']);
            if ($tenantId && (int)$selected->tenant_id !== (int)$tenantId) {
                throw \Illuminate\Validation\ValidationException::withMessages(['assignment_id'=>'Choose an assignment belonging to the selected tenant.']);
            }
            $tenantId = $selected->tenant_id;
        }
        $scopedAssignments = StallAssignment::with(["tenant", "agreement.assessment"])
                ->where("status", "active")
                ->whereHas("tenant", function ($query) {
                    $query->where("status", "active");
                })
                ->whereHas("agreement", function ($query) {
                    $query->whereNotNull("rental_assessment_id");
                })
                ->when($tenantId,function ($query) use ($tenantId) { $query->where('tenant_id',$tenantId); })
                ->orderBy("tenant_id")
                ->get();
        [$fixed, $monthly] = $scopedAssignments->partition(function (StallAssignment $assignment) {
            return optional($assignment->agreement->assessment)->isFixedDays();
        });
        return view("contracts.create", [
            'assignments' => $monthly,
            'hasFixedDayAssignments' => $fixed->isNotEmpty(),
            "templates" => ContractTemplate::where("approved", true)->get(),
            "submissionKey" => (string) Str::uuid(),
            "selectedTenant" => $tenantId ? \App\Models\Tenant::findOrFail($tenantId) : null,
        ]);
    }
    public function store(LeaseContractRequest $r, LeaseContractService $service)
    {
        $c = $service->create($r->user(), $r->validated());
        return redirect()
            ->route("contracts.show", $c)
            ->with(
                "success",
                "Contract draft saved with the frozen rental assessment.",
            );
    }
    public function show(LeaseContract $contract)
    {
        $this->authorize("contracts.view");
        $contract->load(["tenant", "assignment.stall", "template", "assessment"]);
        $activities = \App\Models\AuditLog::where("entity_type", "LeaseContract")
            ->where("entity_id", $contract->id)
            ->orderBy("id")
            ->get();
        return view("contracts.show", compact("contract", "activities"));
    }
    public function generate(Request $r, LeaseContract $contract, LeaseContractService $service)
    {
        $service->generate($r->user(), $contract, $r->all());
        return back()->with(
            "success",
            "Original DOCX and encrypted snapshot preserved. Complete page-by-page visual review before approval.",
        );
    }
    public function transition(
        Request $r,
        LeaseContract $contract,
        $action,
        LeaseContractService $service
    ) {
        $service->transition($r->user(), $contract, $action, $r->all());
        return back()->with(
            "success",
            "Contract action recorded. Stall occupancy was not changed.",
        );
    }
    public function upload(Request $r, LeaseContract $contract, LeaseContractService $service)
    {
        $service->uploadSigned($r->user(), $contract, $r->file("signed_file"));
        return back()->with(
            "success",
            "Signed copy retained. Activation remains a separate authorized action.",
        );
    }
    public function download(
        Request $r,
        LeaseContract $contract,
        $format,
        LeaseContractService $service
    ) {
        $this->authorize($r->boolean("print") ? "contracts.print" : "contracts.download");
        abort_unless(in_array($format, ["docx", "pdf", "signed"], true), 404);
        $path = $service->privatePath(
            $contract->{$format . "_path"},
            $contract->{$format . "_checksum"},
        );
        Audit::record(
            $r->user(),
            $r->boolean("print") ? "contract.print" : "contract.download",
            $contract,
        );
        $extension = $format === "signed" ? pathinfo($path, PATHINFO_EXTENSION) : $format;
        return response()->download($path, $contract->control_number . "." . $extension, [
            "Cache-Control" => "private, no-store",
            "X-Content-Type-Options" => "nosniff",
        ]);
    }
    public function templates()
    {
        $this->authorize("contract_templates.manage");
        return view("contracts.templates", ["templates" => ContractTemplate::latest("id")->get()]);
    }
    public function registerTemplate(Request $r, ContractTemplateService $service)
    {
        $this->authorize("contract_templates.manage");
        $d = $r->validate([
            "version" => "required|string|max:80|unique:contract_templates,version",
            "name" => "required|string|max:150",
        ]);
        $source = storage_path(
            "app/private/references/v02_San_Pedro_Public_Market_Lease_Template.docx",
        );
        abort_unless(is_file($source), 422, "The authoritative source DOCX is missing.");
        $path = "private/contract-templates/" . Str::uuid() . ".docx";
        Storage::disk("local")->makeDirectory("private/contract-templates");
        try {
            $meta = $service->prepare($source, storage_path("app/" . $path));
            $t = ContractTemplate::create(
                $d + [
                    "path" => $path,
                    "source_checksum" => hash_file("sha256", $source),
                    "checksum" => hash_file("sha256", storage_path("app/" . $path)),
                    "created_by" => $r->user()->id,
                ],
            );
            Audit::record($r->user(), "contract_template.registered", $t);
        } catch (\Throwable $e) {
            Storage::disk("local")->delete($path);
            throw $e;
        }
        return back()->with(
            "success",
            "Working template registered privately. Approval requires authorized visual verification.",
        );
    }
    public function approveTemplate(
        Request $r,
        ContractTemplate $template,
        LeaseContractService $service
    ) {
        $this->authorize("contract_templates.approve");
        $d = $r->validate([
            "approval_reference" => "required|string|max:300",
            "effective_date" => "required|date_format:Y-m-d",
            "visual_review_confirmed" => "accepted",
        ]);
        $service->privatePath($template->path, $template->checksum);
        $template->fill(
            $d + ["approved" => true, "approved_by" => $r->user()->id, "approved_at" => now()],
        );
        unset($template->visual_review_confirmed);
        $template->save();
        Audit::record($r->user(), "contract_template.approved", $template);
        return back()->with("success", "Template approval recorded.");
    }
    public function downloadTemplate(ContractTemplate $template, LeaseContractService $service)
    {
        $this->authorize("contract_templates.manage");
        return response()->download(
            $service->privatePath($template->path, $template->checksum),
            "template-" . $template->id . ".docx",
            ["Cache-Control" => "private, no-store"],
        );
    }
}
