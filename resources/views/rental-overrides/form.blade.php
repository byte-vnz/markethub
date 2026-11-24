@extends('layouts.app')
@section('title',$draft->exists ? 'Edit Rental Override Draft' : 'Request Manual Rental Override')
@section('subtitle',$tenant->name.' · '.$assignment->stall->code.' · '.(optional($assignment->business)->name ?? 'Business unavailable'))
@section('content')
@php
    $context = compact('tenant', 'assignment', 'period');
    $target = $draft->exists ? route('rental-overrides.update', $context + ['override'=>$draft]) : route('rental-overrides.store', $context);
    $oldScalar = static function ($key, $value = null) use ($recoverInput) {
        $input = $recoverInput ? old($key, $value) : $value;
        return is_scalar($input) ? $input : $value;
    };
    $errorApplies = $earliest && $recoverInput;
    $type = $oldScalar('override_type', $draft->override_type);
@endphp
<section class="panel max-w-4xl">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2>Proposed rental terms</h2>
            <p class="text-sm text-slate-600 mt-2">Current section {{ optional($assignment->stall->section)->name ?? 'Unknown' }} · Return to reference month {{ $period }}</p>
        </div>
        @can('rentals.override.history')
            <a class="btn secondary" href="{{ route('rental-overrides.index',$context) }}">Override history</a>
        @else
            <a class="btn secondary" href="{{ route('records.show',['tenants',$tenant->id,'tab'=>auth()->user()->can('billing.view') ? 'rental' : 'overview','period'=>$period]) }}">Tenant record</a>
        @endcan
    </div>
    @if($assessment)
        <p class="text-sm mt-4">Frozen automatic basis · {{ $assessment->business_category_name }} · {{ ucfirst($assessment->location_type) }} · {{ $assessment->floor_area }} sq. m.</p>
        <p class="text-sm text-slate-600 mt-1">PHP {{ $assessment->rental_rate_sqm }} per sq. m. · PHP {{ $assessment->daily_rental }} daily · Approved range PHP {{ $assessment->minimum_rate }}–{{ $assessment->maximum_rate }} per sq. m.</p>
    @endif
    @if(!$earliest)
        <p class="notice mt-4">This assignment has no eligible full prospective month. The saved draft remains in history.</p>
    @endif
    @if($draft->exists && !$recoverInput && session()->hasOldInput('override_type'))
        <p class="notice mt-4" role="status">Showing the latest saved draft. Review these terms and re-enter any edits you want to keep.</p>
    @endif
    @if($draft->source_override_id)
        <p class="text-sm text-slate-600 mt-3">Revision of request #{{ $draft->source_override_id }}. Enter the revised terms below.</p>
    @endif
    <form action="{{ $target }}" method="post" class="mt-5"
          data-rental-override data-preview-url="{{ route('rental-overrides.preview',compact('tenant','assignment')) }}">
        @csrf
        @if($draft->exists) @method('PUT') <input type="hidden" name="draft_fingerprint" value="{{ $fingerprint }}"> @endif
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="submission_key" value="{{ $draft->exists ? $draft->submission_key : $oldScalar('submission_key',$draft->submission_key) }}">
        @if($draft->source_override_id)<input type="hidden" name="source_override_id" value="{{ $draft->source_override_id }}">@endif
        <fieldset @unless($earliest) disabled @endunless>
            <legend class="sr-only">Amount and effective period</legend>
            <div class="grid gap-x-4 sm:grid-cols-2">
                <div>
                    <label for="override_type">Override method (required)</label>
                    <select id="override_type" name="override_type" required @include('components.field-error-attributes', ['field'=>'override_type','id'=>'override_type'])>
                        <option value="rate_per_sqm" @if($type === 'rate_per_sqm') selected @endif>Rate per square meter</option>
                        <option value="daily_amount" @if($type === 'daily_amount') selected @endif>Direct daily amount</option>
                    </select>@include('components.field-error', ['field'=>'override_type','id'=>'override_type'])
                </div>
                @foreach(['rate_per_sqm'=>['override_rate_per_sqm','Rate per square meter (PHP)'],'daily_amount'=>['override_daily_amount','Daily rent (PHP)']] as $method=>[$field,$label])
                    <div data-override-amount="{{ $method }}" @unless($type === $method) hidden @endunless>
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="text" inputmode="decimal" maxlength="15" pattern="[0-9]+(\.[0-9]{1,2})?"
                               value="{{ $oldScalar($field,$draft->$field) }}" @if($type === $method) required @else disabled @endif @include('components.field-error-attributes', ['field'=>$field,'id'=>$field,'errorApplies'=>$errorApplies && $type === $method])>@include('components.field-error', ['field'=>$field,'id'=>$field,'errorApplies'=>$errorApplies && $type === $method])
                    </div>
                @endforeach
                <div>
                    <label for="effective_from">First day of effective month (required)</label>
                    <input id="effective_from" name="effective_from" type="date" value="{{ $oldScalar('effective_from',$draft->effective_from ?? $defaultStart) }}"
                           @if($earliest) min="{{ $earliest }}" @endif @if($lastFullMonth) max="{{ $lastFullMonth }}" @endif required @include('components.field-error-attributes', ['field'=>'effective_from','id'=>'effective_from'])>@include('components.field-error', ['field'=>'effective_from','id'=>'effective_from'])
                </div>
                <div>
                    <label for="effective_until">Last day of final month{{ $assignment->end_date ? ' (required)' : ' (optional)' }}</label>
                    <input id="effective_until" name="effective_until" type="date" value="{{ $oldScalar('effective_until',$draft->effective_until ?? $lastFullMonth) }}"
                           @if($lastFullMonth) max="{{ $lastFullMonth }}" required @endif @include('components.field-error-attributes', ['field'=>'effective_until','id'=>'effective_until'])>@include('components.field-error', ['field'=>'effective_until','id'=>'effective_until'])
                </div>
            </div>
            <p class="text-sm text-slate-600 mt-2">Choose full prospective billing months. @if($earliest)Earliest available start {{ $earliest }}.@endif</p>
            <div class="mt-4">
                <button class="btn secondary" type="button" data-override-preview hidden>Preview proposed rent</button>
                <div aria-live="polite" class="text-sm" data-override-result></div>
                <noscript><p class="text-sm text-slate-600">Enable JavaScript to switch amount methods and preview the proposed rent.</p></noscript>
            </div>
            <label for="override_reason">Reason (required)</label>
            <textarea id="override_reason" name="reason" rows="3" maxlength="1000" required @include('components.field-error-attributes', ['field'=>'reason','id'=>'override_reason'])>{{ $oldScalar('reason',$draft->reason) }}</textarea>@include('components.field-error', ['field'=>'reason','id'=>'override_reason'])
            <label for="reference_document">Supporting reference</label>
            <input id="reference_document" name="reference_document" value="{{ $oldScalar('reference_document',$draft->reference_document) }}" maxlength="255"
                    @include('components.field-error-attributes', ['field'=>'reference_document','id'=>'reference_document','hint'=>'override-reference-help'])>@include('components.field-error', ['field'=>'reference_document','id'=>'reference_document','hint'=>'override-reference-help'])
            <p class="text-sm text-slate-600 mt-1" id="override-reference-help">Zero or out-of-range rent requires a supporting reference and explicit policy authority.</p>
            <label for="override_remarks">Remarks</label>
            <textarea id="override_remarks" name="remarks" rows="2" maxlength="2000" @include('components.field-error-attributes', ['field'=>'remarks','id'=>'override_remarks'])>{{ $oldScalar('remarks',$draft->remarks) }}</textarea>@include('components.field-error', ['field'=>'remarks','id'=>'override_remarks'])
            <div class="flex flex-wrap gap-2 mt-5">
                @if($draft->exists)
                    <button class="btn" type="submit">Save draft changes</button>
                @else
                    <button class="btn secondary" type="submit" name="intent" value="save">Save draft</button>
                    <button class="btn" type="submit" name="intent" value="submit">Submit for review</button>
                @endif
            </div>
        </fieldset>
    </form>
    @if($draft->exists && $earliest && (!$recoverInput || !session()->hasOldInput('override_type')))
        <form method="post" action="{{ route('rental-overrides.submit',$context + ['override'=>$draft]) }}" class="mt-5 border-t border-slate-200 pt-4"
              data-confirm="Submit the saved draft for review?">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="hidden" name="draft_fingerprint" value="{{ $fingerprint }}">
            <input type="hidden" name="draft_terms_hash" value="{{ $draft->request_hash }}">
            <p class="text-sm text-slate-600 mb-2">Submission freezes the saved terms. Save any edits before submitting.</p>
            <button class="btn secondary" type="submit" data-submit-saved-draft>Submit saved draft</button>
        </form>
    @endif
</section>
<script defer src="{{ asset('assets/rental-overrides.js') }}"></script>
@endsection
