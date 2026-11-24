@php($decisionId = $action.'-'.$override->id)
@php($errorApplies = old('decision_id') === $decisionId)
<details class="mt-4" @if(old('decision_id') === $decisionId && $errors->any()) open @endif>
    <summary class="cursor-pointer text-sm font-semibold text-blue-900">{{ $label }}</summary>
    <form class="max-w-xl mt-2" method="post" action="{{ route('rental-overrides.'.$action,$rowContext) }}" data-confirm="{{ $label }}?">
        @csrf
        <input type="hidden" name="decision_id" value="{{ $decisionId }}">
        @if($action === 'revoke')
            <label for="{{ $decisionId }}-date">First day of return month (required)</label>
            <input id="{{ $decisionId }}-date" type="date" name="revocation_effective_from" required
                   value="{{ old('decision_id') === $decisionId && is_string(old('revocation_effective_from')) ? old('revocation_effective_from') : '' }}" @include('components.field-error-attributes', ['field'=>'revocation_effective_from','id'=>$decisionId.'-date'])>@include('components.field-error', ['field'=>'revocation_effective_from','id'=>$decisionId.'-date'])
            <p class="text-sm text-slate-600 mt-2">Choose a prospective first of the month outside frozen billing and contract obligations.</p>
        @endif
        <label for="{{ $decisionId }}-reason">Reason (required)</label>
        <textarea id="{{ $decisionId }}-reason" name="reason" maxlength="1000" rows="2" required @include('components.field-error-attributes', ['field'=>'reason','id'=>$decisionId.'-reason'])>{{ old('decision_id') === $decisionId && is_string(old('reason')) ? old('reason') : '' }}</textarea>@include('components.field-error', ['field'=>'reason','id'=>$decisionId.'-reason'])
        <button class="btn danger mt-3" type="submit">{{ $label }}</button>
    </form>
</details>
