<?php
namespace App\Services;

use App\Http\Requests\TenantRequest;
use App\Models\{Tenant, TenantHelper, StallAssignment, User};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TenantDataService
{
    public function save(User $actor, array $data, $id = null)
    {
        abort_unless($actor->active && $actor->can('tenants.manage'),403);
        return DB::transaction(function () use ($actor,$data,$id) {
            $action = $data['registration_action'] ?? null;
            $pending = $data['pending_assignments'] ?? [];
            $businessData = $data['businesses'] ?? [];
            $version = $data['registration_version'] ?? null;
            if ($action === 'confirm' && $pending) {
                abort_unless($actor->can('assignments.manage'), 403);
            }
            if ($action) {
                $submission = $this->claimSubmission($actor, $data, $id);
                if ($submission->tenant_id !== null) {
                    return Tenant::findOrFail($submission->tenant_id);
                }
            } elseif ($pending || $businessData) {
                throw ValidationException::withMessages(['registration_action'=>'Save a draft or confirm the unified registration.']);
            }
            $tenant = $id ? Tenant::whereKey($id)->lockForUpdate()->firstOrFail() : new Tenant();
            if ($action) {
                if ($tenant->exists && $tenant->status !== 'draft') {
                    throw ValidationException::withMessages(['registration_action'=>'This registration is already closed. Manage its businesses and assignments from the tenant profile.']);
                }
                if ($tenant->exists && ($tenant->registration_draft['version'] ?? null) !== $version) {
                    throw ValidationException::withMessages(['registration_version'=>'This draft changed in another session. Reload it before continuing.']);
                }
                $data['status'] = $action === 'draft' ? 'draft' : 'active';
                Validator::make($data, ['reference'=>['nullable', Rule::unique('tenants', 'reference')->ignore($id)]])->validate();
            } elseif (($data['status'] ?? null) === 'draft' || $tenant->status === 'draft') {
                throw ValidationException::withMessages(['status'=>'Use the registration form to continue this draft.']);
            }
            if (($data['status'] ?? 'active') !== 'active' && $tenant->exists && $tenant->assignments()->where('status','active')->exists()) {
                throw ValidationException::withMessages(['status'=>'End active assignments before archiving or deactivating this tenant.']);
            }
            $old = $tenant->getAttributes();
            $helpers = $data['helpers'] ?? [];
            $assignmentId = $data['helper_assignment_id'] ?? null;
            $data = Arr::except($data, ['helpers','helper_assignment_id','profile_form','registration_action','submission_key','registration_version','businesses','pending_assignments']);
            if (!empty($data['last_name']) && !empty($data['first_name'])) {
                $initial = trim($data['middle_initial'] ?? '');
                if ($initial === '' && !empty($data['middle_name'])) {
                    $initial = mb_substr(trim($data['middle_name']),0,1);
                }
                $data['middle_initial'] = $initial !== '' ? rtrim(mb_strtoupper($initial),'.') . '.' : null;
                $data['name'] = mb_strtoupper(trim($data['last_name']).', '.trim($data['first_name'])) . ($data['middle_initial'] ? ', '.$data['middle_initial'] : '');
                if (mb_strlen($data['name']) > 255) {
                    throw ValidationException::withMessages(['last_name'=>'The formatted name must not exceed 255 characters.']);
                }
            } elseif ($tenant->exists) {
                // Legacy names remain intact until staff supplies both structured name fields.
                unset($data['last_name'],$data['first_name'],$data['middle_name'],$data['middle_initial']);
                if (empty($data['name'])) {
                    unset($data['name']);
                }
            }
            if (!$tenant->exists && empty($data['reference'])) {
                $data['reference'] = 'T-'.Str::uuid();
            } elseif ($tenant->exists && empty($data['reference'])) {
                unset($data['reference']);
            }
            if (empty($data['government_id_number'])) {
                unset($data['government_id_number']);
            }
            if (empty($data['citizenship'])) {
                $data['citizenship'] = $tenant->citizenship ?: 'Filipino';
            }
            $tenant->fill($data)->save();
            if ($assignmentId) {
                $assignment = StallAssignment::whereKey($assignmentId)->where('tenant_id',$tenant->id)->lockForUpdate()->first();
                if (!$assignment) {
                    throw ValidationException::withMessages(['helper_assignment_id'=>'Choose an assignment belonging to this tenant.']);
                }
                $this->saveHelpers($actor, $assignment, $helpers);
            } elseif (array_filter($helpers, function ($h) { return !empty($h['full_name']); })) {
                throw ValidationException::withMessages(['helper_assignment_id'=>'Choose an existing assignment before registering helpers.']);
            }
            Audit::record($actor,$id ? 'tenants.updated' : 'tenants.created',$tenant,$old,$data);
            if ($action) {
                $this->saveRegistration($actor, $tenant, $action, $businessData, $pending);
                DB::table('tenant_registration_submissions')->where('actor_id', $actor->id)
                    ->where('submission_key', $submission->submission_key)->update(['tenant_id'=>$tenant->id]);
            }
            return $tenant;
        },3);
    }

    private function claimSubmission(User $actor, array $data, $id)
    {
        $key = strtolower($data['submission_key']);
        $hash = hash_hmac('sha256', json_encode([$id, $data], JSON_THROW_ON_ERROR), config('app.key'));
        $submissions = DB::table('tenant_registration_submissions');
        $submissions->insertOrIgnore(['actor_id'=>$actor->id,'submission_key'=>$key,'request_hash'=>$hash,'created_at'=>now()]);
        $submission = $submissions->where('actor_id', $actor->id)->where('submission_key', $key)->lockForUpdate()->first();
        if (!hash_equals($submission->request_hash, $hash)) {
            throw ValidationException::withMessages(['submission_key'=>'This submission has already been used. Reload the registration form before submitting changes.']);
        }
        return $submission;
    }

    private function saveRegistration(User $actor, Tenant $tenant, string $action, array $businessData, array $pending): void
    {
        $businesses = [];
        $seen = [];
        foreach ($businessData as $key => $data) {
            if (!preg_match('/\A[a-zA-Z0-9_-]{1,40}\z/', (string)$key)) {
                throw ValidationException::withMessages(['businesses'=>'Each business must have a valid registration key.']);
            }
            $id = $data['id'] ?? null;
            if ($id !== null && (isset($seen[$id]) || !$tenant->businesses()->whereKey($id)->exists())) {
                throw ValidationException::withMessages(['businesses'=>'Each listed business must belong to this tenant and appear only once.']);
            }
            $business = app(BusinessService::class)->save($actor, $data, $id, $tenant->id);
            $businesses[$key] = $business->id;
            $seen[$business->id] = true;
        }
        $assignments = [];
        foreach (array_values($pending) as $index => $selection) {
            $businessId = $businesses[$selection['business_key']] ?? null;
            if (!$businessId) {
                throw ValidationException::withMessages(['pending_assignments.'.$index.'.business_key'=>'Choose a business from this registration.']);
            }
            $selection = Arr::only($selection, ['stall_id','start_date','end_date','concurrent_authorization_reference',
                'concurrent_authorization_reason','helpers','billing_interval_days','daily_cusa']);
            $selection['helpers'] = Validator::make(['helpers'=>$selection['helpers'] ?? []], TenantRequest::helperRules('helpers'))->validate()['helpers'];
            $selection['business_id'] = $businessId;
            if ($action === 'draft') {
                $assignments[] = $selection;
                continue;
            }
            app(AssignmentService::class)->assign($actor, $selection + [
                'tenant_id'=>$tenant->id,
                'agreement_reference'=>'RA-'.Str::uuid(),
            ]);
        }
        $tenant->registration_draft = $action === 'draft' ? [
            'version'=>(string)Str::uuid(), 'businesses'=>$businesses, 'assignments'=>$assignments,
        ] : null;
        $tenant->save();
    }

    public function saveHelpers(User $actor, StallAssignment $assignment, array $helpers): void
    {
        abort_unless($actor->active && ($actor->can('tenants.manage') || $actor->can('assignments.manage')), 403);
        $helpers = array_values(Validator::make(['helpers'=>$helpers], TenantRequest::helperRules('helpers'))->validate()['helpers']);
        for ($slot = 1; $slot <= 2; $slot++) {
            $helper = $helpers[$slot-1] ?? [];
            if (trim($helper['full_name'] ?? '') === '') {
                TenantHelper::where('stall_assignment_id',$assignment->id)->where('slot',$slot)->update(['status'=>'inactive']);
                continue;
            }
            TenantHelper::updateOrCreate(['stall_assignment_id'=>$assignment->id,'slot'=>$slot], [
                'tenant_id'=>$assignment->tenant_id,'full_name'=>$helper['full_name'],'relationship'=>$helper['relationship'] ?? null,
                'phone'=>$helper['phone'] ?? null,'status'=>$helper['status'] ?? 'authorized','remarks'=>$helper['remarks'] ?? null,
            ]);
        }
        Audit::record($actor,'tenant.helpers.updated',$assignment->tenant,[],['helper_assignment_id'=>$assignment->id]);
    }

    public function hasDuplicate(Tenant $tenant): bool
    {
        $name = mb_strtolower(preg_replace('/\s+/u', '', $tenant->name));
        $accounts = array_values(array_filter([$tenant->previous_account_number, $tenant->present_account_number], function ($account) {
            return $account !== null && $account !== '';
        }));
        $phone = preg_replace('/[^0-9]+/', '', $tenant->phone ?? '');

        return Tenant::where('id', '<>', $tenant->id)->where(function ($query) use ($name, $accounts, $phone) {
            $query->whereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [$name]);
            if ($accounts) {
                $query->orWhereIn('present_account_number', $accounts)->orWhereIn('previous_account_number', $accounts);
            }
            if ($phone !== '') {
                $query->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?", [$phone]);
            }
        })->exists();
    }

    public function contractData(Tenant $tenant, StallAssignment $assignment)
    {
        if ((int)$assignment->tenant_id !== (int)$tenant->id) {
            throw ValidationException::withMessages(['tenant'=>'The assignment does not belong to this tenant.']);
        }
        $helpers = $tenant->helpers()->where('stall_assignment_id',$assignment->id)->where('status','authorized')->orderBy('slot')->limit(2)->pluck('full_name')->values();
        $issued = implode(' / ',array_filter([$tenant->government_id_date_issued,$tenant->government_id_place_issued]));
        if ((!$tenant->government_id_date_issued || !$tenant->government_id_place_issued) && $tenant->government_id_issuance_exception_reason) {
            $issued .= ($issued ? ' / ' : '').'Not specified on ID: '.$tenant->government_id_issuance_exception_reason;
        }
        return [
            'lessee_full_name'=>$tenant->name,
            'civil_status'=>$tenant->civil_status ? ucfirst(strtolower($tenant->civil_status)) : null,
            'gender'=>$tenant->gender,
            'citizenship'=>$tenant->citizenship,
            'residential_address'=>$tenant->address,
            'contact_number'=>$tenant->phone,
            'government_id_type'=>$tenant->government_id_type,
            'government_id_number'=>$tenant->government_id_number,
            'id_date_place_issued'=>$issued,
            'government_id_date_issued'=>$tenant->government_id_date_issued,
            'government_id_place_issued'=>$tenant->government_id_place_issued,
            'government_id_issuance_exception_reason'=>$tenant->government_id_issuance_exception_reason,
            'helper_1'=>$helpers[0] ?? '',
            'helper_2'=>$helpers[1] ?? '',
        ];
    }
}
