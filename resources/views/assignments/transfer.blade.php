@extends('layouts.app')
@section('title','Transfer or renew assignment')
@section('subtitle',config('billing.fixed_day_enabled')
    ? 'The current assignment ends before the new agreement begins. Choose the billing interval for the new term.'
    : 'The current assignment ends when the new agreement begins. The new term uses the approved category rate and market policy.')
@section('content')
<form method="post" action="{{ route('assignments.transfer',$assignment) }}" class="panel max-w-2xl" data-confirm="Save transfer or renewal?">
    @csrf
    <p>{{ $assignment->tenant->name }} · {{ $assignment->stall->code }}</p>
    @if($stalls->isEmpty() || $businesses->isEmpty())
        <p class="notice">Choose a classified stall and verify an active business with a category on the tenant profile before continuing.</p>
    @endif
    <label for="stall">Destination stall (same stall for renewal)</label>
    <select id="stall" name="stall_id" required>
        <option value="">Select a stall</option>
        @foreach($stalls as $s)
            <option value="{{ $s->id }}" @if(old('stall_id',$assignment->stall_id)==$s->id) selected @endif>{{ $s->market->name }} · {{ $s->code }} · {{ ucfirst($s->location_type) }}</option>
        @endforeach
    </select>
    <label for="business">Business for the new agreement</label>
    <select id="business" name="business_id" required>
        <option value="">Select a business</option>
        @foreach($businesses as $b)
            <option value="{{ $b->id }}" @if(old('business_id',$assignment->business_id)==$b->id) selected @endif>
                {{ $b->businessCategory->name }}@if($b->name) · {{ $b->name }}@endif
            </option>
        @endforeach
    </select>
    <label for="start">New start date{{ $originalInterval ? '' : ' (first day of a month)' }}</label>
    <input id="start" type="date" name="start_date" value="{{ old('start_date',$originalInterval ? '' : today()->startOfMonth()->toDateString()) }}" max="{{ today()->toDateString() }}" required
        @include('components.field-error-attributes', ['field'=>'start_date','id'=>'start','hint'=>$originalInterval ? 'transfer-start-hint' : null])>
    @if($originalInterval)
        <p class="text-sm text-slate-600 mt-1" id="transfer-start-hint">Use a {{ $originalInterval }}-day interval boundary after the original start on {{ $assignment->start_date }}.@unless(config('billing.fixed_day_enabled')) The new monthly term must also start on the first day of a month.@endunless</p>
    @endif
    @include('components.field-error', ['field'=>'start_date','id'=>'start'])
    <label for="end">New end date (optional)</label>
    <input id="end" type="date" name="end_date" value="{{ old('end_date') }}">
    @include('assignments.billing-terms')
    <label for="reference">New agreement reference</label>
    <input id="reference" name="agreement_reference" value="{{ old('agreement_reference') }}" maxlength="120" required>
    <label for="reason">Transfer or renewal reason</label>
    <textarea id="reason" name="reason" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea>
    @include('assignments.concurrency')
    <button class="btn mt-6" @if($stalls->isEmpty() || $businesses->isEmpty()) disabled @endif>Save transfer or renewal</button>
</form>
@endsection
