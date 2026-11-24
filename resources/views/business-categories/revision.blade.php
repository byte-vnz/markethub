@php($errorApplies = old('form_key') === 'retire-'.$formKey)
<article class="panel mb-4" aria-labelledby="heading-{{ $formKey }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h3 id="heading-{{ $formKey }}" class="font-semibold">
            @if ($policyForm){{ $schedule->market->name }} · @endif
            {{ ucfirst($schedule->status) }} revision #{{ $schedule->id }}
        </h3>
        @can('rates.approve')
            @if ($schedule->status === 'draft')
                <form method="post" action="{{ $approveUrl }}">
                    @csrf
                    <input type="hidden" name="draft_fingerprint" value="{{ \App\Services\BusinessCategoryService::draftFingerprint($schedule) }}">
                    <button class="btn secondary">Approve {{ $policyForm ? 'policy' : 'rate' }}</button>
                </form>
            @endif
        @endcan
    </div>

    <dl class="grid gap-3 mt-3 sm:grid-cols-2 xl:grid-cols-3">
        @if ($policyForm)
            <div><dt class="text-sm text-slate-600">Daily CUSA</dt><dd>₱{{ $schedule->daily_cusa }}</dd></div>
            <div><dt class="text-sm text-slate-600">Billing days</dt><dd>{{ $schedule->billing_day_rule === 'calendar' ? 'Calendar days' : $schedule->fixed_billing_days.' fixed days' }}</dd></div>
            <div><dt class="text-sm text-slate-600">Maximum concurrent assignments</dt><dd>{{ $schedule->max_concurrent_assignments }}</dd></div>
            <div><dt class="text-sm text-slate-600">Advance and deposit authorized</dt><dd>{{ $schedule->advance_deposit_authorized ? 'Yes' : 'No' }}</dd></div>
            <div><dt class="text-sm text-slate-600">Out-of-range rentals allowed</dt><dd>{{ $schedule->allow_out_of_range_overrides ? 'Yes' : 'No' }}</dd></div>
            <div><dt class="text-sm text-slate-600">Zero-rent exemptions allowed</dt><dd>{{ $schedule->allow_zero_rent_overrides ? 'Yes' : 'No' }}</dd></div>
            <div><dt class="text-sm text-slate-600">Override authority reference</dt><dd>{{ $schedule->override_authority_reference ?? '—' }}</dd></div>
        @else
            <div><dt class="text-sm text-slate-600">Inner minimum (₱/m²/day)</dt><dd>{{ $schedule->minimum_rate ?? '—' }}</dd></div>
            <div><dt class="text-sm text-slate-600">Outer maximum (₱/m²/day)</dt><dd>{{ $schedule->maximum_rate ?? '—' }}</dd></div>
        @endif
        <div><dt class="text-sm text-slate-600">Effective period</dt><dd>{{ $schedule->effective_from ? $schedule->effective_from->toDateString().' to '.(optional($schedule->effective_until)->toDateString() ?? 'open') : 'Undated' }}</dd></div>
        <div class="break-words"><dt class="text-sm text-slate-600">Official reference</dt><dd>{{ $schedule->reference_document ?? '—' }}</dd></div>
        @if ($schedule->approved_at)
            <div><dt class="text-sm text-slate-600">Approved</dt><dd>{{ $schedule->approved_at->toDateString() }} · {{ optional($schedule->approver)->name ?? '—' }}</dd></div>
        @endif
        @if ($schedule->remarks)
            <div class="break-words"><dt class="text-sm text-slate-600">Remarks</dt><dd>{{ $schedule->remarks }}</dd></div>
        @endif
    </dl>

    @can('rates.manage')
        @if ($schedule->status === 'draft')
            <details class="mt-4" @if (old('form_key') === $formKey) open @endif>
                <summary class="cursor-pointer font-semibold">Edit draft</summary>
                @include('business-categories.schedule-form', ['action' => $updateUrl])
            </details>
        @endif
    @endcan
    @can('rates.approve')
        @if ($schedule->status === 'approved' && (!$schedule->effective_until || $schedule->effective_until->gte(today())))
            <details class="mt-4" @if (old('form_key') === 'retire-'.$formKey) open @endif>
                <summary class="cursor-pointer font-semibold">Retire revision</summary>
                <form class="mt-4" method="post" action="{{ $retireUrl }}">
                    @csrf
                    <input type="hidden" name="form_key" value="retire-{{ $formKey }}">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="{{ $formKey }}-retire-until">Last effective date (inclusive)</label>
                            <input id="{{ $formKey }}-retire-until" type="date" name="effective_until" min="{{ max(today()->toDateString(), optional($schedule->effective_from)->toDateString()) }}" @if ($schedule->effective_until) max="{{ $schedule->effective_until->toDateString() }}" @endif value="{{ old('form_key') === 'retire-'.$formKey ? old('effective_until') : null }}" required @include('components.field-error-attributes', ['field'=>'effective_until','id'=>$formKey.'-retire-until'])>
                            @include('components.field-error', ['field'=>'effective_until','id'=>$formKey.'-retire-until'])
                        </div>
                        <div>
                            <label for="{{ $formKey }}-retire-reason">Retirement reason</label>
                            <textarea id="{{ $formKey }}-retire-reason" name="reason" maxlength="500" required @include('components.field-error-attributes', ['field'=>'reason','id'=>$formKey.'-retire-reason'])>{{ old('form_key') === 'retire-'.$formKey ? old('reason') : null }}</textarea>@include('components.field-error', ['field'=>'reason','id'=>$formKey.'-retire-reason'])
                        </div>
                    </div>
                    <button class="btn secondary mt-4">Retire revision</button>
                </form>
            </details>
        @endif
    @endcan
</article>
