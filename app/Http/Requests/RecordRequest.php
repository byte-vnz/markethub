<?php
namespace App\Http\Requests;
use App\Support\Resources;
use App\Services\SectionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RecordRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() &&
            $this->user()->active &&
            $this->user()->can(Resources::get($this->route("type"))["permission"] . ".manage");
    }
    public function rules()
    {
        $type = $this->route("type");
        if ($type === 'tenants') {
            return TenantRequest::tenantRules($this);
        }
        if ($type === 'businesses') {
            return BusinessRequest::businessRules($this, false);
        }
        if ($type === 'sections') {
            return SectionService::rules();
        }
        $id = $this->route("id");
        $rules = [];
        $spec = Resources::get($type);
        foreach ($spec["fields"] as $field => $meta) {
            $rules[$field] = "nullable|string|max:255";
            if ($meta[1] === "textarea") {
                $rules[$field] = "nullable|string|max:2000";
            }
            if ($meta[1] === "date") {
                $rules[$field] = "nullable|date_format:Y-m-d";
            }
            if ($meta[1] === "relation") {
                $modelClass = $meta[2];
                $model = new $modelClass();
                $rules[$field] = "nullable|integer|exists:" . $model->getTable() . ",id";
            }
            if ($meta[1] === "select") {
                $rules[$field] = ["required", Rule::in(array_keys($meta[2]))];
            }
        }
        foreach (["name", "code", "reference", "classification", "approval_reference"] as $field) {
            if (isset($rules[$field])) {
                $rules[$field] =
                    "required|string|max:" . (in_array($field, ["code", "reference"]) ? 40 : 120);
            }
        }
        foreach (["market_id", "market_section_id", "tenant_id"] as $field) {
            if (isset($rules[$field])) {
                $rules[$field] = str_replace("nullable", "required", $rules[$field]);
            }
        }
        if ($type === "markets") {
            $rules["code"] = [
                "required",
                "string",
                "max:40",
                Rule::unique("markets", "code")->ignore($id),
            ];
        }
        if ($type === "stalls") {
            $rules['location_type'] = [$id ? 'nullable' : 'required', Rule::in(['inner','outer'])];
            $rules['change_reason'] = 'nullable|string|min:5|max:1000';
            $rules["code"] = [
                "required",
                "string",
                "max:40",
                Rule::unique("stalls", "code")->where("market_id", $this->market_id)->ignore($id),
            ];
            $rules["area"] = 'required|numeric|min:0|max:99999999.99|regex:/^\d+(\.\d{1,2})?$/';
            $rules["market_section_id"] = [
                "required",
                "integer",
                Rule::exists("market_sections", "id")->where("market_id", $this->market_id),
            ];
            if ($id && \App\Models\Stall::findOrFail($id)->status === "occupied") {
                $rules["status"] = ["required", Rule::in(["occupied"])];
            }
        }
        if ($type === "tenants") {
            $rules["reference"] = [
                "required",
                "string",
                "max:60",
                Rule::unique("tenants", "reference")->ignore($id),
            ];
            $rules["email"] = "nullable|email|max:255";
            $rules["phone"] = "nullable|string|max:40";
        }
        if ($type === "categories") {
            $rules["name"] = [
                "required",
                "string",
                "max:120",
                Rule::unique("stall_categories", "name")->ignore($id),
            ];
        }
        if ($type === "rates") {
            $rules["amount"] = 'required|regex:/^\d{1,12}(\.\d{1,2})?$/|numeric|min:0.01';
            $rules["effective_from"] = "required|date_format:Y-m-d";
            $rules["effective_until"] = "nullable|date_format:Y-m-d|after_or_equal:effective_from";
        }
        return $rules;
    }
}
