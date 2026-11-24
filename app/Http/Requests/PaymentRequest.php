<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class PaymentRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can("payments.record");
    }
    protected function prepareForValidation()
    {
        if (is_array($this->allocations)) {
            $this->merge([
                "allocations" => array_filter($this->allocations, function ($v) {
                    return $v !== null && $v !== "" && $v !== "0" && $v !== "0.00";
                }),
            ]);
        }
    }
    public function rules()
    {
        return [
            "replacement_of_id" => "nullable|integer|exists:payments,id",
            "tenant_id" => "required|integer|exists:tenants,id",
            "payment_date" => "required|date_format:Y-m-d",
            "amount" => 'required|regex:/^\d{1,12}(\.\d{1,2})?$/',
            "method" => "required|in:cash,bank_transfer,check",
            "receipt_reference" => "required|string|max:120",
            "idempotency_key" => "required|uuid",
            "allocations" => "required|array|min:1|max:100",
            "allocations.*" => 'required|regex:/^\d{1,12}(\.\d{1,2})?$/',
            "remarks" => "nullable|string|max:2000",
        ];
    }
}
