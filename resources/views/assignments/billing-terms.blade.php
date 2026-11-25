@if(config('billing.fixed_day_enabled'))
    @php
        $intervalField = isset($billingFieldPrefix) ? $billingFieldPrefix.'[billing_interval_days]' : 'billing_interval_days';
        $cusaField = isset($billingFieldPrefix) ? $billingFieldPrefix.'[daily_cusa]' : 'daily_cusa';
        $intervalId = ($billingIdPrefix ?? 'assignment').'-billing-days';
        $cusaId = ($billingIdPrefix ?? 'assignment').'-daily-cusa';
        $defaultDays = config('billing.default_interval_days', 7);
        $intervalValue = isset($billingFieldPrefix) ? ($selection['billing_interval_days'] ?? $defaultDays) : old('billing_interval_days', $defaultDays);
        $cusaValue = isset($billingFieldPrefix) ? ($selection['daily_cusa'] ?? '') : old('daily_cusa');
    @endphp
    <div>
        <label for="{{ $intervalId }}">Billing interval (days)</label>
        <input type="number" id="{{ $intervalId }}" name="{{ $intervalField }}" min="1" max="366" step="1"
            value="{{ is_scalar($intervalValue) ? $intervalValue : '' }}" data-billing-days
            @include('components.field-error-attributes', ['field'=>$intervalField,'id'=>$intervalId,'hint'=>$intervalId.'-hint'])>
        <p id="{{ $intervalId }}-hint" class="text-sm text-slate-600 mt-1">Leave blank to use {{ $defaultDays }} days.</p>
        @include('components.field-error', ['field'=>$intervalField,'id'=>$intervalId])
    </div>
    <div>
        <label for="{{ $cusaId }}">Daily CUSA for records only (PHP)</label>
        <input id="{{ $cusaId }}" name="{{ $cusaField }}" inputmode="decimal" pattern="[0-9]{1,12}(\.[0-9]{1,2})?" maxlength="15"
            value="{{ is_scalar($cusaValue) ? $cusaValue : '' }}" data-daily-cusa
            @include('components.field-error-attributes', ['field'=>$cusaField,'id'=>$cusaId,'hint'=>$cusaId.'-hint'])>
        <p id="{{ $cusaId }}-hint" class="text-sm text-slate-600 mt-1">Optional. Bills charge rent only.</p>
        @include('components.field-error', ['field'=>$cusaField,'id'=>$cusaId])
    </div>
@endif
