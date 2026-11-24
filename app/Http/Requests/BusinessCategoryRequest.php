<?php

namespace App\Http\Requests;

use App\Services\BusinessCategoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessCategoryRequest extends FormRequest
{
    public function authorize()
    {
        $approval = $this->routeIs('*.approve', '*.retire');
        return $this->user()->active && $this->user()->can($approval ? 'rates.approve' : 'rates.manage');
    }

    public function rules()
    {
        if ($this->routeIs('*.approve')) {
            return ['draft_fingerprint' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']];
        }
        if ($this->routeIs('*.retire')) {
            return BusinessCategoryService::retirementRules();
        }
        if ($this->routeIs('business-categories.rates.*')) {
            return BusinessCategoryService::scheduleRules(false);
        }
        if ($this->routeIs('rental-policies.*')) {
            return BusinessCategoryService::scheduleRules(true) + ($this->routeIs('rental-policies.store')
                ? ['market_id' => 'required|integer|exists:markets,id'] : []);
        }

        $uniqueName = Rule::unique('business_categories', 'name');
        $category = $this->route('category');
        if ($category) {
            $uniqueName->ignore($category);
        }
        return [
            'code' => $category ? 'prohibited' : ['required', 'string', 'max:40', 'regex:/\A[A-Z0-9]+(?:-[A-Z0-9]+)*\z/', 'unique:business_categories,code'],
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'description' => 'nullable|string|max:2000',
            'sort_order' => 'required|integer|between:0,4294967295',
            'is_active' => 'required|boolean',
        ];
    }
}
