<?php
namespace App\Services;
use App\Models\{User, Tenant, StallAssignment, LeaseContract, ContractTemplate};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{DB, Validator, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class LeaseContractService
{
    private function authorize(User $actor, $permission)
    {
        abort_unless($actor->active && $actor->can($permission), 403);
    }
    private function check($condition, $message)
    {
        if (!$condition) {
            throw ValidationException::withMessages(["contract" => $message]);
        }
    }
    public function create(User $actor, array $input)
    {
        $this->authorize($actor, "contracts.create");
        $d = Validator::make($input, self::draftRules())->validate();
        $d = Arr::only($d, ["stall_assignment_id", "contract_template_id", "start_date", "end_date",
            "execution_date", "submission_key", "tenant_id"]);
        $d["request_hash"] = hash("sha256", json_encode($d));
        $a = StallAssignment::findOrFail($d["stall_assignment_id"]);
        return DB::transaction(function () use ($actor, $d, $a) {
            Tenant::whereKey($a->tenant_id)->lockForUpdate()->firstOrFail();
            $a = StallAssignment::whereKey($a->id)->lockForUpdate()->firstOrFail();
            $this->check(empty($d['tenant_id']) || (int)$d['tenant_id'] === (int)$a->tenant_id,
                'The assignment must belong to the selected tenant.');
            $existing = LeaseContract::where("submission_key", $d["submission_key"])->first();
            if ($existing) {
                $this->check(
                    $existing->created_by === $actor->id &&
                        $existing->stall_assignment_id === $a->id &&
                        hash_equals($existing->request_hash, $d["request_hash"]),
                    "This submission key belongs to another request.",
                );
                return $existing;
            }
            $template = ContractTemplate::findOrFail($d['contract_template_id']);
            $this->check($template->source_checksum === ContractTemplateService::V04_SHA256,
                'New contract drafts require the reviewed v04 template.');
            $this->eligible($a, $d["start_date"], $d["end_date"]);
            $agreement = $a->agreement;
            $this->check($agreement && $agreement->assessment,
                "Renew this assignment before creating a contract from an approved category assessment.");
            $initial = $agreement->assessment;
            $this->check(!$initial->isFixedDays(),
                'The monthly contract template cannot be used for fixed-day agreements. A compatible approved template and workflow are required.');
            $business = $a->business()->lockForUpdate()->first();
            $tradeFields = ["name", "line_of_business", "permitted_goods"];
            $this->check($business && $business->status === "active" &&
                (int) $business->tenant_id === (int) $a->tenant_id &&
                (int) $business->business_category_id === (int) $initial->business_category_id &&
                $business->only($tradeFields) === Arr::only($initial->snapshot["business"], $tradeFields),
                "Renew this assignment before using changed business or trade details in a new contract.");
            $goods = $initial->snapshot["business"]["permitted_goods"];
            $this->check(is_string($goods) && trim($goods) !== "" && mb_strlen($goods) <= 180,
                "Verify permitted goods of 1 to 180 characters and renew the assignment before creating a contract.");
            $this->check(
                !LeaseContract::where("stall_assignment_id", $a->id)
                    ->whereNotIn("status", ["void", "terminated"])
                    ->where("start_date", "<=", $d["end_date"])
                    ->where("end_date", ">=", $d["start_date"])
                    ->exists(),
                "An overlapping contract already exists. Void or terminate it through the authorized workflow first.",
            );
            $period = substr($d["start_date"], 0, 7);
            app(RentalOverrideService::class)->guardContractTerms($a, $d['start_date'], $d['end_date']);
            $assessment = app(RentalCalculationService::class)->assess($actor, $a, $period, "contract");
            $values = $assessment->only([
                "floor_area", "rental_rate_sqm", "daily_cusa", "billing_days", "daily_rental",
                "monthly_rental", "monthly_cusa", "total_monthly_rental_cusa",
                "advance_rental", "security_deposit", "total_initial_payment",
            ]);
            $contract = LeaseContract::create(
                array_merge($d, $values, [
                    "tenant_id" => $a->tenant_id,
                    "rental_assessment_id" => $assessment->id,
                    "permitted_goods" => $goods,
                    "control_number" => app(ContractNumberService::class)->next(),
                    "status" => "draft",
                    "created_by" => $actor->id,
                ]),
            );
            Audit::record($actor, "contract.created", $contract);
            return $contract;
        }, 3);
    }

    public static function draftRules(): array
    {
        return [
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            "stall_assignment_id" => "required|integer|exists:stall_assignments,id",
            "contract_template_id" => "required|integer|exists:contract_templates,id",
            "start_date" => "required|date_format:Y-m-d",
            "end_date" => "required|date_format:Y-m-d|after:start_date",
            "execution_date" => "required|date_format:Y-m-d",
            "billing_days" => "prohibited",
            "rental_rate_sqm" => "prohibited",
            "daily_cusa" => "prohibited",
            "permitted_goods" => "prohibited",
            "submission_key" => "required|uuid",
        ];
    }
    private function eligible(StallAssignment $a, $start, $end)
    {
        $this->check(
            $a->tenant->status === "active" && $a->status === "active",
            "Choose an active tenant and existing active assignment.",
        );
        $this->check(
            $start >= $a->start_date && (!$a->end_date || $end <= $a->end_date),
            "Lease period must fall within the existing assignment dates.",
        );
    }
    public function generate(User $actor, LeaseContract $contract, array $signatory)
    {
        $this->authorize($actor, "contracts.generate");
        $d = Validator::make($signatory, [
            "lessor_name" => "required|string|max:120",
            "lessor_id_type" => "nullable|string|max:80",
            "lessor_id_number" => "nullable|string|max:80",
            "lessor_id_date_place_issued" => "nullable|string|max:160",
        ])->validate();
        return $this->locked($contract, function ($c) use ($actor, $d) {
            $this->check(
                $c->status === "draft",
                "Only a draft may be generated. Reprints retrieve the original file.",
            );
            $this->eligible($c->assignment, $c->start_date, $c->end_date);
            $assessment = $c->assessment;
            $this->check((bool) $assessment,
                "Renew this assignment and create a new contract draft with an approved assessment.");
            app(RentalOverrideService::class)->guardContractTerms($c->assignment, $c->start_date, $c->end_date, $assessment);
            $template = $c->template;
            $this->check(
                $template->approved && $template->effective_date <= $c->execution_date,
                "Use an approved template effective on the execution date.",
            );
            $this->check(
                hash_equals(
                    $template->checksum,
                    hash_file("sha256", $this->privatePath($template->path)),
                ),
                "Template checksum changed; register and approve a new version.",
            );
            $this->check(
                !empty($c->tenant->last_name) && !empty($c->tenant->first_name),
                "Supply verified structured first and last names before generation.",
            );
            $tenant = app(TenantDataService::class)->contractData($c->tenant, $c->assignment);
            $this->check(
                !empty($tenant["lessee_full_name"]) &&
                    !empty($tenant["residential_address"]) &&
                    !empty($tenant["contact_number"]) &&
                    !empty($tenant["government_id_type"]) &&
                    !empty($tenant["government_id_number"]),
                "Complete verified tenant name, address, phone and government identification before generation.",
            );
            $this->check(
                in_array($tenant["civil_status"] ?? null, ["Single", "Married"], true),
                "The source supports Single/Married only. Other civil status mappings need authorized template review.",
            );
            $this->check(
                (!empty($tenant["government_id_date_issued"]) &&
                    !empty($tenant["government_id_place_issued"])) ||
                    !empty($tenant["government_id_issuance_exception_reason"]),
                "Provide both ID issuance date and place, or document a legitimate omission for authorized review.",
            );
            $this->check(
                !empty($tenant["id_date_place_issued"]),
                "Provide verified ID issuance details or an explicitly recorded legitimate omission reason.",
            );
            // Keep the approved source lessor wording; changing signatories requires a new template.
            $this->check(
                $d["lessor_name"] === "HON. ART JOSEPH FRANCIS MERCADO",
                "Lessor name must match the approved source template; register a reviewed version for a different signatory.",
            );
            $dayRule = $assessment->basis["billing_day_rule"] === "calendar" ? "calendar days" : "fixed policy days";
            $snapshot = array_merge($tenant, $d, [
                "contract_control_no" => $c->control_number,
                "stall_code" => $assessment->stall_code,
                "stall_category" => $assessment->basis["stall_section_name"] . " / " .
                    $assessment->business_category_name . " / " . ucfirst($assessment->location_type),
                "stall_location" => ucfirst($assessment->location_type),
                "lease_period" => $c->start_date . " – " . $c->end_date,
                "permitted_goods" => $template->source_checksum === ContractTemplateService::V04_SHA256
                    ? trim(implode(' / ', array_filter([
                        $assessment->snapshot['business']['line_of_business'] ?? null,
                        $c->permitted_goods,
                    ], function ($value) { return $value !== null && trim((string) $value) !== ''; })))
                    : $c->permitted_goods,
                "billing_basis" => $assessment->period . " reference month, " . $c->billing_days .
                    " " . $dayRule . ", base rental plus CUSA",
                "rental_assessment_id" => $assessment->id,
                "business_category_id" => $assessment->business_category_id,
                "business_category_name" => $assessment->business_category_name,
                "business_category_rental_rate_id" => $assessment->business_category_rental_rate_id,
                "rental_policy_id" => $assessment->rental_policy_id,
                "location_type" => $assessment->location_type,
                "rental_basis" => $assessment->basis,
                "computation_mode" => $assessment->computation_mode,
                "rental_override_id" => $assessment->rental_override_id,
                "automatic_rate_per_sqm" => $assessment->automatic_rate_per_sqm ?? $assessment->rental_rate_sqm,
                "automatic_daily_rental" => $assessment->automatic_daily_rental ?? $assessment->daily_rental,
                "rental_difference" => $assessment->rental_difference,
                "equivalent_rate_per_sqm" => $assessment->equivalent_rate_per_sqm,
                "market_section_id" => $assessment->market_section_id,
                "market_section_name" => $assessment->market_section_name ?? ($assessment->basis['stall_section_name'] ?? null),
                "business" => $assessment->snapshot["business"],
                "execution_day" => date("j", strtotime($c->execution_date)),
                "execution_month" => date("F", strtotime($c->execution_date)),
                "execution_year" => date("Y", strtotime($c->execution_date)),
                "template_version" => $template->version,
            ]);
            foreach (
                [
                    "floor_area",
                    "rental_rate_sqm",
                    "daily_cusa",
                    "daily_rental",
                    "monthly_rental",
                    "monthly_cusa",
                    "total_monthly_rental_cusa",
                    "advance_rental",
                    "security_deposit",
                    "total_initial_payment",
                ]
                as $key
            ) {
                $snapshot[$key] = (string) $c->$key;
            }
            $folder = "private/contracts/" . Str::uuid();
            Storage::disk("local")->makeDirectory($folder);
            $docx = $folder . "/original.docx";
            $absolute = storage_path("app/" . $docx);
            try {
                app(ContractGenerationService::class)->render(
                    $this->privatePath($template->path),
                    $snapshot,
                    $absolute,
                );
                $pdf = app(ContractPdfConversionService::class)->convert(
                    $absolute,
                    dirname($absolute),
                );
                $c->fill([
                    "snapshot" => $snapshot,
                    "docx_path" => $docx,
                    "docx_checksum" => hash_file("sha256", $absolute),
                    "pdf_path" => $pdf ? $folder . "/" . basename($pdf) : null,
                    "pdf_checksum" => $pdf ? hash_file("sha256", $pdf) : null,
                    "generated_by" => $actor->id,
                    "generated_at" => now(),
                    "status" => "ready",
                ])->save();
            } catch (\Throwable $e) {
                Storage::disk("local")->deleteDirectory($folder);
                throw $e;
            }
            Audit::record($actor, "contract.generated", $c);
            return $c;
        });
    }
    public function transition(User $actor, LeaseContract $contract, $action, array $input = [])
    {
        $permissions = [
            "approve" => "contracts.approve",
            "issue" => "contracts.issue",
            "signed" => "contracts.upload_signed",
            "activate" => "contracts.issue",
            "expire" => "contracts.issue",
            "terminate" => "contracts.void",
            "void" => "contracts.void",
        ];
        abort_unless(isset($permissions[$action]), 404);
        $this->authorize($actor, $permissions[$action]);
        return $this->locked($contract, function ($c) use ($actor, $action, $input) {
            if ($action === "approve") {
                $d = Validator::make($input, [
                    "visual_review_reference" => "required|string|max:300",
                    "visual_review_confirmed" => "accepted",
                ])->validate();
                $this->check($c->status === "ready", "Generate the document before visual review.");
                $c->approved_by = $actor->id;
                $c->approved_at = now();
                $c->visual_review_reference = $d["visual_review_reference"];
            } elseif ($action === "issue") {
                $this->check(
                    $c->status === "ready" && $c->approved_at,
                    "Document and nine-page visual review must be approved before issuance.",
                );
                $this->check($c->template->approved && $c->template->effective_date <= $c->execution_date,
                    "The document must use an approved template effective on the execution date.");
                $this->check(
                    (bool) config("lease.numbering_approval") &&
                        (bool) config("lease.rental_policy_approval"),
                    "Configure approved numbering and rental-policy references before issuance.",
                );
                $this->check(!$c->assessment || !empty($c->assessment->basis["advance_deposit_authorized"]),
                    "The frozen rental policy must authorize advance rental and the security deposit before issuance.");
                $this->eligible($c->assignment, $c->start_date, $c->end_date);
                $this->check(
                    $c->end_date >= today()->toDateString(),
                    "An expired lease cannot be issued.",
                );
                app(RentalOverrideService::class)->guardContractTerms($c->assignment, $c->start_date, $c->end_date, $c->assessment);
                $this->privatePath($c->docx_path, $c->docx_checksum);
                $c->status = "issued";
                $c->issued_by = $actor->id;
                $c->issued_at = now();
            } elseif ($action === "activate") {
                $this->check(
                    $c->status === "signed" && $c->signed_path,
                    "Upload the executed signed document before activation.",
                );
                $this->eligible($c->assignment, $c->start_date, $c->end_date);
                $this->check(
                    $c->start_date <= today()->toDateString() &&
                        $c->end_date >= today()->toDateString(),
                    "The lease must currently be in effect.",
                );
                $c->status = "active";
                $c->activated_at = now();
            } elseif ($action === "expire") {
                $this->check(
                    in_array($c->status, ["issued", "signed", "active"], true) &&
                        $c->end_date < today()->toDateString(),
                    "Only an elapsed lease may be marked expired.",
                );
                $c->status = "expired";
                $c->closed_at = now();
            } elseif (in_array($action, ["void", "terminate"], true)) {
                $d = Validator::make($input, [
                    "reason" => "required|string|min:10|max:1000",
                ])->validate();
                $this->check(
                    !in_array($c->status, ["void", "terminated", "expired"], true),
                    "This contract is already closed.",
                );
                $c->status = $action === "void" ? "void" : "terminated";
                $c->reason = $d["reason"];
                $c->closed_at = now();
            } else {
                throw ValidationException::withMessages([
                    "contract" => "Use the authenticated signed-upload action.",
                ]);
            }
            $c->save();
            Audit::record($actor, "contract." . $action, $c, [], [], $c->reason);
            return $c;
        });
    }
    public function uploadSigned(User $actor, LeaseContract $contract, $file)
    {
        $this->authorize($actor, "contracts.upload_signed");
        Validator::make(
            ["signed_file" => $file],
            ["signed_file" => "required|file|mimes:pdf,jpg,jpeg,png|max:15360"],
        )->validate();
        return $this->locked($contract, function ($c) use ($actor, $file) {
            $this->check(
                $c->status === "issued" && !$c->signed_path,
                "Only an issued contract without a signed copy may receive one.",
            );
            $path = $file->store("private/contracts/signed", "local");
            try {
                $c->fill([
                    "signed_path" => $path,
                    "signed_checksum" => hash_file("sha256", storage_path("app/" . $path)),
                    "signed_at" => now(),
                    "status" => "signed",
                ])->save();
            } catch (\Throwable $e) {
                Storage::disk("local")->delete($path);
                throw $e;
            }
            Audit::record($actor, "contract.signed", $c);
            return $c;
        });
    }
    private function locked(LeaseContract $contract, $callback)
    {
        return DB::transaction(function () use ($contract, $callback) {
            Tenant::whereKey($contract->tenant_id)->lockForUpdate()->firstOrFail();
            StallAssignment::whereKey($contract->stall_assignment_id)->lockForUpdate()->firstOrFail();
            return $callback(
                LeaseContract::whereKey($contract->id)->lockForUpdate()->firstOrFail(),
            );
        }, 3);
    }
    public function privatePath($relative, $checksum = null)
    {
        $base = realpath(storage_path("app/private"));
        $path = is_string($relative) ? realpath(storage_path("app/" . $relative)) : false;
        abort_unless(
            $base &&
                $path &&
                strpos(str_replace("\\", "/", $path), str_replace("\\", "/", $base) . "/") === 0 &&
                is_file($path),
            404,
        );
        if ($checksum) {
            abort_unless(
                hash_equals($checksum, hash_file("sha256", $path)),
                409,
                "Stored document integrity check failed.",
            );
        }
        return $path;
    }
}
