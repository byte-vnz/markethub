<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateBillingRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->active && $this->user()->can('billing.generate');
    }

    public function rules()
    {
        $fixedDays = $this->input('billing_mode') === 'fixed_days';

        return [
            'assignment_ids' => 'required|array|min:1|max:500',
            'assignment_ids.*' => 'required|integer|distinct|exists:stall_assignments,id',
            'billing_mode' => 'sometimes|required|in:monthly,fixed_days',
            'period' => $fixedDays ? 'prohibited' : 'required|date_format:Y-m',
            'period_start' => $fixedDays ? 'required|date_format:Y-m-d' : 'prohibited',
            'due_date' => 'required|date_format:Y-m-d',
        ];
    }

    public function attributes()
    {
        return ['period'=>'billing month', 'period_start'=>'fixed-day period start'];
    }
}
