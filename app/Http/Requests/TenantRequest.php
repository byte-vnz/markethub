<?php
namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->active && $this->user()->can('tenants.manage');
    }

    public function rules()
    {
        return self::tenantRules($this);
    }

    public static function tenantRules($request)
    {
        $id = $request->route('id');
        $existing = $id ? Tenant::findOrFail($id) : null;
        $structured = $request->filled('last_name') || $request->filled('first_name');
        $requireNames = $structured || (!$existing && $request->boolean('profile_form'));
        $registrationAction = $request->input('registration_action');
        $referenceRules = ['nullable','string','max:60'];
        if (!$registrationAction) {
            $referenceRules[] = Rule::unique('tenants','reference')->ignore($id);
        }
        $rules = [
            'profile_form'=>'nullable|boolean',
            'reference'=>$referenceRules,
            'name'=>($requireNames || $existing ? 'nullable' : 'required').'|string|max:255',
            'last_name'=>($requireNames ? 'required' : 'nullable').'|string|max:100',
            'first_name'=>($requireNames ? 'required' : 'nullable').'|string|max:100',
            'middle_name'=>'nullable|string|max:100',
            'middle_initial'=>'nullable|string|max:10',
            'civil_status'=>['nullable',Rule::in(['single','married','widowed','separated','other'])],
            'gender'=>['nullable',Rule::in(array_keys(config('tenants.genders', [])))],
            'citizenship'=>'nullable|string|max:100',
            'phone'=>'nullable|string|max:40',
            'email'=>'nullable|email|max:255',
            'address'=>'nullable|string|max:2000',
            'previous_account_number'=>'nullable|string|max:120',
            'present_account_number'=>'nullable|string|max:120',
            'government_id_type'=>'nullable|string|max:100',
            'government_id_number'=>'nullable|string|max:120',
            'government_id_date_issued'=>'nullable|date_format:Y-m-d|before_or_equal:today',
            'government_id_place_issued'=>'nullable|string|max:255',
            'government_id_issuance_exception_reason'=>'nullable|string|max:2000',
            'status'=>['required_without:registration_action',Rule::in(['draft','active','inactive','archived'])],
            'remarks'=>'nullable|string|max:2000',
            'helper_assignment_id'=>['nullable','integer',Rule::exists('stall_assignments','id')->where('tenant_id',$id ?: 0)],
            'registration_action'=>'nullable|in:draft,confirm',
            'submission_key'=>'required_with:registration_action|nullable|uuid',
            'registration_version'=>'nullable|uuid',
            'businesses'=>'nullable|array|max:10',
            'businesses.*'=>'array',
            'businesses.*.id'=>'nullable|integer',
            'pending_assignments'=>'nullable|array|max:10',
            'pending_assignments.*'=>'array',
            'pending_assignments.*.business_key'=>'required|string|max:40|regex:/\A[a-zA-Z0-9_-]+\z/',
            'pending_assignments.*.stall_id'=>'required|integer|distinct|exists:stalls,id',
            'pending_assignments.*.start_date'=>'required|date_format:Y-m-d|before_or_equal:today',
            'pending_assignments.*.end_date'=>'nullable|date_format:Y-m-d|after_or_equal:pending_assignments.*.start_date',
            'pending_assignments.*.concurrent_authorization_reference'=>'nullable|string|max:255',
            'pending_assignments.*.concurrent_authorization_reason'=>'nullable|string|max:2000',
        ];
        foreach (BusinessRequest::fields(true) as $field => $rule) {
            $rules['businesses.*.'.$field] = $rule;
        }
        return $rules + self::helperRules('helpers') + self::helperRules('pending_assignments.*.helpers');
    }

    public static function helperRules(string $key): array
    {
        return [
            $key=>'nullable|array|max:2',
            $key.'.*'=>'array',
            $key.'.*.full_name'=>'nullable|string|max:150',
            $key.'.*.relationship'=>'nullable|string|max:100',
            $key.'.*.phone'=>'nullable|string|max:40',
            $key.'.*.status'=>'nullable|in:authorized,inactive',
            $key.'.*.remarks'=>'nullable|string|max:2000',
        ];
    }
}
