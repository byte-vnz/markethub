@extends('layouts.app')
@section('title','Assign a tenant')
@section('subtitle',config('billing.fixed_day_enabled')
    ? 'The agreement uses the approved category rate, stall location and selected billing interval.'
    : 'The agreement uses the approved business category rate, stall location and market rental policy.')
@section('content')
<form method="post" action="{{ route('assignments.store') }}" class="panel max-w-3xl">
    @csrf
    @if($stalls->isEmpty() || $businesses->isEmpty())
        <p class="notice">An available classified stall and an active tenant business with an active category are required.</p>
    @endif
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label for="stall">Stall</label>
            <select id="stall" name="stall_id" required>
                <option value="">Select a stall</option>
                @foreach($stalls as $s)
                    <option value="{{ $s->id }}" @if(old('stall_id',request('stall_id'))==$s->id) selected @endif>
                        {{ $s->market->name }} · {{ $s->code }} · {{ ucfirst($s->location_type) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="tenant">Tenant</label>
            <select id="tenant" name="tenant_id" required>
                <option value="">Select a tenant</option>
                @foreach($tenants as $t)
                    <option value="{{ $t->id }}" @if(old('tenant_id',request('tenant_id'))==$t->id) selected @endif>{{ $t->reference }} · {{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="business">Business belonging to the tenant</label>
            <select id="business" name="business_id" required>
                <option value="">Select a business</option>
                @foreach($businesses as $b)
                    <option value="{{ $b->id }}" @if(old('business_id',request('business_id'))==$b->id) selected @endif>
                        {{ $b->tenant->reference }} · {{ $b->name ?: $b->businessCategory->name }}@if($b->name) · {{ $b->businessCategory->name }}@endif
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="start">Start date</label>
            <input id="start" type="date" name="start_date" value="{{ old('start_date',config('billing.fixed_day_enabled') ? today()->toDateString() : today()->startOfMonth()->toDateString()) }}" required>
        </div>
        <div>
            <label for="end">End date (optional)</label>
            <input id="end" type="date" name="end_date" value="{{ old('end_date') }}">
        </div>
        @include('assignments.billing-terms')
        <div>
            <label for="reference">Agreement reference (optional)</label>
            <input id="reference" name="agreement_reference" value="{{ old('agreement_reference') }}" maxlength="120" aria-describedby="reference-hint">
            <p id="reference-hint" class="text-sm text-slate-600 mt-1">Leave blank to generate an internal agreement reference.</p>
        </div>
        <div>
            <label for="remarks">Remarks</label>
            <input id="remarks" name="remarks" value="{{ old('remarks') }}" maxlength="2000">
        </div>
    </div>
    @include('assignments.concurrency')
    <button class="btn mt-6" @if($stalls->isEmpty() || $businesses->isEmpty()) disabled @endif>Save assignment</button>
</form>
@endsection
