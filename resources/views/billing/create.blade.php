@extends('layouts.app')
@section('title','Generate draft bills')
@section('subtitle','Select assignments and review each draft before posting.')
@section('content')
@php
    $fixed = $mode === 'fixed_days';
    $periodField = $fixed ? 'period_start' : 'period';
    $periodValue = old($periodField, $fixed ? '' : today()->format('Y-m'));
    $dueDate = old('due_date');
    $selectedAssignments = (array) old('assignment_ids', []);
@endphp
@if($showFixed)
    <nav aria-label="Billing mode" class="flex flex-wrap gap-2 mb-5">
        <a class="btn {{ $fixed ? 'secondary' : '' }}" href="{{ route('billing.create') }}" @unless($fixed) aria-current="page" @endunless>Monthly</a>
        <a class="btn {{ $fixed ? '' : 'secondary' }}" href="{{ route('billing.create', ['billing_mode'=>'fixed_days']) }}" @if($fixed) aria-current="page" @endif>Fixed-day</a>
    </nav>
@endif
<form method="post" action="{{ route('billing.generate') }}" class="panel">
    @csrf
    <input type="hidden" name="billing_mode" value="{{ $mode }}">
    <div class="toolbar">
        <div>
            <label for="period">{{ $fixed ? 'Fixed-day period start' : 'Billing month' }} (required)</label>
            <input id="period" type="{{ $fixed ? 'date' : 'month' }}" name="{{ $periodField }}"
                value="{{ is_scalar($periodValue) ? $periodValue : '' }}" @if($fixed) max="{{ today()->toDateString() }}" @endif required
                @include('components.field-error-attributes', ['field'=>$periodField,'id'=>'period','hint'=>'period-hint'])>
            <p id="period-hint" class="text-sm text-slate-600 mt-1">{{ $fixed ? 'Use the assignment start or the first day of a later full interval.' : 'The agreement must cover the full month.' }}</p>
            @include('components.field-error', ['field'=>$periodField,'id'=>'period'])
        </div>
        <div>
            <label for="due">Approved due date (required)</label>
            <input id="due" type="date" name="due_date" value="{{ is_scalar($dueDate) ? $dueDate : '' }}" required
                @include('components.field-error-attributes', ['field'=>'due_date','id'=>'due'])>
            @include('components.field-error', ['field'=>'due_date','id'=>'due'])
        </div>
    </div>
    @if($fixed)<p class="notice mt-5">Bills charge rent only. CUSA is recorded in the assessment.</p>@endif
    <fieldset class="mt-5 space-y-3">
        <legend class="font-semibold">Assignments</legend>
        @forelse($assignments as $assignment)
            @php($checkboxId = 'bill-assignment-'.$assignment->id)
            <div class="flex gap-3 items-center border-b pb-3">
                <input id="{{ $checkboxId }}" type="checkbox" name="assignment_ids[]" value="{{ $assignment->id }}"
                    @if(in_array($assignment->id, $selectedAssignments)) checked @endif
                    @if($loop->first) @include('components.field-error-attributes', ['field'=>'assignment_ids','id'=>$checkboxId]) @endif>
                <label for="{{ $checkboxId }}" class="m-0">
                    {{ $assignment->stall->code }} · {{ $assignment->tenant->name }}
                    @if($fixed)
                        · PHP {{ $assignment->agreement->monthly_amount }} per {{ $assignment->agreement->assessment->billing_days }} days
                        · starts {{ $assignment->start_date }}
                    @elseif($assignment->agreement)
                        · PHP {{ $assignment->agreement->monthly_amount }} per reference month
                    @endif
                </label>
            </div>
            @if($loop->first) @include('components.field-error', ['field'=>'assignment_ids','id'=>$checkboxId]) @endif
        @empty
            <p class="empty">No active {{ $fixed ? 'fixed-day' : 'monthly' }} assignments are available for billing.</p>
        @endforelse
    </fieldset>
    @if($assignments->isNotEmpty())<button class="btn mt-6" type="submit">Generate drafts for review</button>@endif
</form>
@endsection
