@extends('layouts.app')
@section('title','Create Lease Contract Draft')
@section('subtitle','The draft uses the selected assignment’s frozen category, permitted goods and rental basis.')
@section('content')
@if($templates->isEmpty())<div class="notice error">An authorized template manager must register and approve the supplied DOCX.</div>@endif
@if($hasFixedDayAssignments)<div class="notice">The approved monthly template is unavailable for fixed-day agreements.</div>@endif
@if($assignments->isEmpty())<div class="notice">An active monthly assignment with an approved rental assessment is required.</div>@endif
<form method="post" action="{{ route('contracts.store') }}" class="space-y-5">
    @csrf
    @if($selectedTenant)<input type="hidden" name="tenant_id" value="{{ $selectedTenant->id }}"><p class="notice">Preparing a lease for {{ $selectedTenant->name }}. Only this tenant’s eligible assignments are listed.</p>@endif
    <input type="hidden" name="submission_key" value="{{ old('submission_key',$submissionKey) }}">
    <section class="panel">
        <h2>Assignment and approved template</h2>
        <label for="stall_assignment_id">Active assessed assignment</label>
        <select required id="stall_assignment_id" name="stall_assignment_id">
            <option value="">Select an assignment</option>
            @foreach($assignments as $a)
                @php($assessment = $a->agreement->assessment)
                <option value="{{ $a->id }}" @if(old('stall_assignment_id',request('assignment_id'))==$a->id) selected @endif>
                    {{ $a->tenant->name }} · {{ $assessment->stall_code }} · {{ $assessment->business_category_name }} · {{ ucfirst($assessment->location_type) }}
                </option>
            @endforeach
        </select>
        <label for="contract_template_id">Approved template version</label>
        <select required id="contract_template_id" name="contract_template_id">
            @foreach($templates as $t)
                <option value="{{ $t->id }}" @if(old('contract_template_id')==$t->id) selected @endif>{{ $t->name }} · {{ $t->version }}</option>
            @endforeach
        </select>
    </section>
    <section class="panel">
        <h2>Lease period</h2>
        <div class="grid md:grid-cols-3 gap-4">
            @foreach(['start_date'=>'Effectivity date','end_date'=>'Expiration date','execution_date'=>'Execution date'] as $key=>$label)
                <div>
                    <label for="{{ $key }}">{{ $label }}</label>
                    <input required type="date" id="{{ $key }}" name="{{ $key }}" value="{{ old($key) }}">
                </div>
            @endforeach
        </div>
        <p class="text-sm text-slate-600 mt-3">The effectivity date’s month is the reference period for the draft’s rent, CUSA, advance and deposit. Under a calendar-day policy, rental totals vary with the number of days in each month.</p>
    </section>
    <button class="btn" @if($templates->isEmpty() || $assignments->isEmpty()) disabled @endif>Save draft</button>
</form>
@endsection
