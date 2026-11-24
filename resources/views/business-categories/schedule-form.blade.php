@php($errorApplies = old('form_key') === $formKey)
@php($fieldValue = fn ($name, $default = null) => old('form_key') === $formKey ? old($name, $default) : $default)
<form class="mt-4" method="post" action="{{ $action }}">
    @csrf
    @if ($schedule) @method('PUT') @endif
    <input type="hidden" name="form_key" value="{{ $formKey }}">
    <div class="grid gap-4 sm:grid-cols-2">
        @if ($policyForm)
            @unless ($schedule)
                <div class="sm:col-span-2">
                    <label for="{{ $formKey }}-market">Market</label>
                    <select id="{{ $formKey }}-market" name="market_id" required @include('components.field-error-attributes', ['field'=>'market_id','id'=>$formKey.'-market'])>
                        <option value="">Choose market</option>
                        @foreach ($markets as $market)
                            <option value="{{ $market->id }}" @if ((int) $fieldValue('market_id', $marketId) === $market->id) selected @endif>{{ $market->name }}</option>
                        @endforeach
                    </select>@include('components.field-error', ['field'=>'market_id','id'=>$formKey.'-market'])
                </div>
            @endunless
            <div>
                <label for="{{ $formKey }}-cusa">Daily CUSA (₱)</label>
                <input id="{{ $formKey }}-cusa" name="daily_cusa" inputmode="decimal" pattern="[0-9]{1,12}(\.[0-9]{1,2})?" value="{{ $fieldValue('daily_cusa', optional($schedule)->daily_cusa) }}" required @include('components.field-error-attributes', ['field'=>'daily_cusa','id'=>$formKey.'-cusa'])>@include('components.field-error', ['field'=>'daily_cusa','id'=>$formKey.'-cusa'])
            </div>
            <div>
                <label for="{{ $formKey }}-cap">Maximum concurrent assignments</label>
                <input id="{{ $formKey }}-cap" type="number" name="max_concurrent_assignments" min="1" max="65535" value="{{ $fieldValue('max_concurrent_assignments', optional($schedule)->max_concurrent_assignments) }}" required @include('components.field-error-attributes', ['field'=>'max_concurrent_assignments','id'=>$formKey.'-cap'])>@include('components.field-error', ['field'=>'max_concurrent_assignments','id'=>$formKey.'-cap'])
            </div>
            <div>
                <label for="{{ $formKey }}-day-rule">Billing-day rule</label>
                <select id="{{ $formKey }}-day-rule" name="billing_day_rule" required @include('components.field-error-attributes', ['field'=>'billing_day_rule','id'=>$formKey.'-day-rule'])>
                    <option value="">Choose billing-day rule</option>
                    <option value="calendar" @if ($fieldValue('billing_day_rule', optional($schedule)->billing_day_rule) === 'calendar') selected @endif>Calendar days</option>
                    <option value="fixed" @if ($fieldValue('billing_day_rule', optional($schedule)->billing_day_rule) === 'fixed') selected @endif>Fixed days</option>
                </select>@include('components.field-error', ['field'=>'billing_day_rule','id'=>$formKey.'-day-rule'])
            </div>
            <div>
                <label for="{{ $formKey }}-fixed-days">Days per billing period (fixed rule only)</label>
                <input id="{{ $formKey }}-fixed-days" type="number" name="fixed_billing_days" min="1" max="366" value="{{ $fieldValue('fixed_billing_days', optional($schedule)->fixed_billing_days) }}" @include('components.field-error-attributes', ['field'=>'fixed_billing_days','id'=>$formKey.'-fixed-days'])>@include('components.field-error', ['field'=>'fixed_billing_days','id'=>$formKey.'-fixed-days'])
            </div>
            <div class="sm:col-span-2">
                <label for="{{ $formKey }}-advance">Advance and deposit authorized</label>
                @php($authorized = $fieldValue('advance_deposit_authorized', $schedule ? (int) $schedule->advance_deposit_authorized : null))
                <select id="{{ $formKey }}-advance" name="advance_deposit_authorized" required @include('components.field-error-attributes', ['field'=>'advance_deposit_authorized','id'=>$formKey.'-advance'])>
                    <option value="">Choose authorization</option>
                    <option value="1" @if ((string) $authorized === '1') selected @endif>Yes</option>
                    <option value="0" @if ((string) $authorized === '0') selected @endif>No</option>
                </select>@include('components.field-error', ['field'=>'advance_deposit_authorized','id'=>$formKey.'-advance'])
            </div>
            @foreach (['allow_out_of_range_overrides' => 'Allow out-of-range rentals', 'allow_zero_rent_overrides' => 'Allow zero-rent exemptions'] as $name => $label)
                <div>
                    <label for="{{ $formKey }}-{{ $name }}">{{ $label }}</label>
                    <select name="{{ $name }}" id="{{ $formKey }}-{{ $name }}" @include('components.field-error-attributes', ['field'=>$name,'id'=>$formKey.'-'.$name])>
                        <option value="0">No</option>
                        <option value="1" @if ($fieldValue($name, optional($schedule)->{$name})) selected @endif>Yes</option>
                    </select>@include('components.field-error', ['field'=>$name,'id'=>$formKey.'-'.$name])
                </div>
            @endforeach
            <div class="sm:col-span-2">
                <label for="{{ $formKey }}-override-reference">Override authority reference</label>
                <input id="{{ $formKey }}-override-reference" name="override_authority_reference" maxlength="255" value="{{ $fieldValue('override_authority_reference', optional($schedule)->override_authority_reference) }}" @include('components.field-error-attributes', ['field'=>'override_authority_reference','id'=>$formKey.'-override-reference'])>@include('components.field-error', ['field'=>'override_authority_reference','id'=>$formKey.'-override-reference'])
                <p class="mt-1 text-sm text-slate-600">Required to approve either rental exception.</p>
            </div>
        @else
            <div>
                <label for="{{ $formKey }}-minimum">Inner minimum (₱/m²/day)</label>
                <input id="{{ $formKey }}-minimum" name="minimum_rate" inputmode="decimal" pattern="[0-9]{1,12}(\.[0-9]{1,2})?" value="{{ $fieldValue('minimum_rate', optional($schedule)->minimum_rate) }}" required @include('components.field-error-attributes', ['field'=>'minimum_rate','id'=>$formKey.'-minimum'])>@include('components.field-error', ['field'=>'minimum_rate','id'=>$formKey.'-minimum'])
            </div>
            <div>
                <label for="{{ $formKey }}-maximum">Outer maximum (₱/m²/day)</label>
                <input id="{{ $formKey }}-maximum" name="maximum_rate" inputmode="decimal" pattern="[0-9]{1,12}(\.[0-9]{1,2})?" value="{{ $fieldValue('maximum_rate', optional($schedule)->maximum_rate) }}" required @include('components.field-error-attributes', ['field'=>'maximum_rate','id'=>$formKey.'-maximum'])>@include('components.field-error', ['field'=>'maximum_rate','id'=>$formKey.'-maximum'])
            </div>
        @endif
        <div>
            <label for="{{ $formKey }}-from">First effective date</label>
            <input id="{{ $formKey }}-from" type="date" name="effective_from" value="{{ $fieldValue('effective_from', optional(optional($schedule)->effective_from)->toDateString()) }}" @include('components.field-error-attributes', ['field'=>'effective_from','id'=>$formKey.'-from'])>@include('components.field-error', ['field'=>'effective_from','id'=>$formKey.'-from'])
        </div>
        <div>
            <label for="{{ $formKey }}-until">Last effective date (optional)</label>
            <input id="{{ $formKey }}-until" type="date" name="effective_until" value="{{ $fieldValue('effective_until', optional(optional($schedule)->effective_until)->toDateString()) }}" @include('components.field-error-attributes', ['field'=>'effective_until','id'=>$formKey.'-until'])>@include('components.field-error', ['field'=>'effective_until','id'=>$formKey.'-until'])
        </div>
        <div class="sm:col-span-2">
            <label for="{{ $formKey }}-reference">Official reference document</label>
            <input id="{{ $formKey }}-reference" name="reference_document" maxlength="255" value="{{ $fieldValue('reference_document', optional($schedule)->reference_document) }}" @include('components.field-error-attributes', ['field'=>'reference_document','id'=>$formKey.'-reference'])>@include('components.field-error', ['field'=>'reference_document','id'=>$formKey.'-reference'])
            <p class="mt-1 text-sm text-slate-600">Approval requires a first effective date and official reference.</p>
        </div>
        <div class="sm:col-span-2">
            <label for="{{ $formKey }}-remarks">Remarks</label>
            <textarea id="{{ $formKey }}-remarks" name="remarks" maxlength="2000" @include('components.field-error-attributes', ['field'=>'remarks','id'=>$formKey.'-remarks'])>{{ $fieldValue('remarks', optional($schedule)->remarks) }}</textarea>@include('components.field-error', ['field'=>'remarks','id'=>$formKey.'-remarks'])
        </div>
    </div>
    <button class="btn mt-4">Save draft</button>
</form>
