<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->active && $this->user()->can('tenants.manage');
    }

    public function rules()
    {
        return self::businessRules($this);
    }

    public static function businessRules($request, $nested = true)
    {
        $rules = self::fields($nested && !$request->route('business'));
        $rules['tenant_id'] = $nested ? 'prohibited' : 'required|integer|exists:tenants,id';
        return $rules;
    }

    public static function fields($categoryRequired = false)
    {
        return [
            'name'=>'nullable|string|max:120',
            'business_category_id'=>($categoryRequired ? 'required' : 'nullable').'|integer|exists:business_categories,id',
            'category'=>'nullable|string|max:255',
            'line_of_business'=>'nullable|string|max:255',
            'permitted_goods'=>'nullable|string|max:2000',
            'description'=>'nullable|string|max:2000',
            'permit_reference'=>'nullable|string|max:255',
            'registration_reference'=>'nullable|string|max:255',
            'permit_from'=>'nullable|date_format:Y-m-d',
            'permit_until'=>'nullable|date_format:Y-m-d',
            'status'=>'required|in:active,inactive,archived',
            'remarks'=>'nullable|string|max:2000',
        ];
    }
}
