<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class AssignmentRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->active && $this->user()->can("assignments.manage");
    }
    public function rules()
    {
        $rules = [
            "stall_id" => "required|integer|exists:stalls,id",
            "business_id" => "required|integer|exists:businesses,id",
            "rental_rate_id" => "prohibited",
            "start_date" => "required|date_format:Y-m-d",
            "end_date" => "nullable|date_format:Y-m-d",
            "agreement_reference" => "nullable|string|max:120",
            "remarks" => "nullable|string|max:2000",
            "concurrent_authorization_reference" => "nullable|string|max:120",
            "concurrent_authorization_reason" => "nullable|required_with:concurrent_authorization_reference|string|min:5|max:1000",
        ];
        return $rules + self::billingTermsRules() + ($this->routeIs("assignments.transfer")
            ? ["reason" => "required|string|min:5|max:1000"]
            : ["tenant_id" => "required|integer|exists:tenants,id"]);
    }

    public static function billingTermsRules(string $prefix = ''): array
    {
        $enabled = config('billing.fixed_day_enabled');

        return [
            $prefix.'billing_interval_days' => $enabled ? 'nullable|integer|between:1,366' : 'prohibited',
            $prefix.'daily_cusa' => $enabled ? 'nullable|regex:/\A[0-9]{1,12}(?:\.[0-9]{1,2})?\z/' : 'prohibited',
        ];
    }
}
