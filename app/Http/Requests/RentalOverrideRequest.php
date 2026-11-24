<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RentalOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('tenant'));
        Gate::authorize('rentals.override.view');
        $action = $this->route()->getActionMethod();
        $decision = in_array($action, ['approve', 'reject', 'revoke'], true);
        if ($decision) {
            Gate::authorize('rentals.override.history');
        }
        Gate::authorize('rentals.override.'.($decision ? $action : 'request'));
        $assignment = $this->route('assignment');
        $override = $this->route('override');
        abort_unless((int) $assignment->tenant_id === (int) $this->route('tenant')->id, 404);
        abort_if($override && (int) $override->stall_assignment_id !== (int) $assignment->id, 404);

        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        $context = ['period'=>'nullable|date_format:Y-m'];
        $reason = ['reason'=>'required|string|max:1000'];
        $terms = [
            'override_type'=>'required|in:rate_per_sqm,daily_amount',
            'override_rate_per_sqm'=>'nullable',
            'override_daily_amount'=>'nullable',
        ] + array_fill_keys(['tenant_id', 'business_id', 'stall_id', 'stall_assignment_id'], 'sometimes|required|integer|min:1');
        $rules = [
            'preview'=>$terms,
            'submit'=>['draft_fingerprint'=>'required|string|size:64', 'draft_terms_hash'=>'required|string|size:64'],
            'approve'=>['fingerprint'=>'required|string|size:64'],
            'reject'=>$reason,
            'revoke'=>$reason + ['revocation_effective_from'=>'required|date_format:Y-m-d'],
        ];
        if (isset($rules[$action])) {
            return $rules[$action] + ($action === 'preview' ? ['period'=>'required|date_format:Y-m'] : $context);
        }
        $draft = $terms + $reason + $context + [
            'effective_from'=>'required|date_format:Y-m-d',
            'effective_until'=>'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'reference_document'=>'nullable|string|max:255',
            'remarks'=>'nullable|string|max:2000',
            'source_override_id'=>'nullable|integer|min:1',
        ];
        return $draft + ($action === 'store'
            ? ['submission_key'=>'required|uuid', 'intent'=>'nullable|in:save,submit']
            : ['submission_key'=>'sometimes|uuid', 'draft_fingerprint'=>'required|string|size:64']);
    }
}
