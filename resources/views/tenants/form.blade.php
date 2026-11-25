@extends('layouts.app')
@section('title', $registration && $record->exists ? 'Resume tenant registration' : ($record->exists ? 'Edit tenant' : 'Register tenant'))
@section('subtitle', $registration ? 'Register the stallholder, businesses and optional stall selections in one form.' : $record->reference)
@section('content')
@if($registration)
<script defer src="{{ asset('assets/tenant-registration.js') }}"></script>
@endif
<nav class="flex flex-wrap gap-x-4 gap-y-2 mb-5 text-sm" aria-label="Tenant form sections">
    @foreach($registration ? ['personal'=>'Personal','identification'=>'Identification','business'=>'Businesses','stall'=>'Stall selections','rental'=>'Rental preview','helpers'=>'Helpers','review'=>'Review'] : ['personal'=>'Personal','identification'=>'Identification','helpers'=>'Helpers','review'=>'Save changes'] as $section=>$label)
        <a class="font-medium text-blue-800 underline underline-offset-2" href="#{{ $section }}">{{ $label }}</a>
    @endforeach
</nav>
<form method="post" action="{{ $record->exists ? route('records.update',['tenants',$record->id]) : route('records.store','tenants') }}" class="max-w-5xl"
    @if($registration)data-registration data-tenant="{{ $record->id }}" @can('assignments.manage')data-preview-url="{{ route('tenants.rental-preview') }}"@endcan @endif>
    @csrf
    @if($record->exists)@method('PUT')@endif
    <input type="hidden" name="profile_form" value="1">
    @if($registration)
        <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        @if(!empty($draft['version']))
            <input type="hidden" name="registration_version" value="{{ session()->hasOldInput('registration_action') ? old('registration_version') : $draft['version'] }}">
        @endif
    @endif
<section class="panel" id="personal"><h2>Personal information</h2>
@if($record->exists)<p class="text-sm text-slate-600 mt-2">Reference: {{ $record->reference }}</p>@endif
@if($record->exists && !$record->last_name)<p class="notice mt-4">Existing name: {{ $record->name }}. Leave both name fields blank to preserve it, or verify and enter the structured name. Existing names are never split automatically.</p>@endif
<div class="grid md:grid-cols-2 gap-x-6 gap-y-2">
@foreach(['last_name'=>'Last name','first_name'=>'First name','middle_name'=>'Middle name','middle_initial'=>'Middle initial'] as $field=>$label)
<div>
    @php($required = !$record->exists && in_array($field,['last_name','first_name']))
    <label for="{{ $field }}">{{ $label }} @if($required) (required) @endif</label>
    <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$record->$field) }}" maxlength="{{ $field==='middle_initial' ? 10 : 100 }}" @if($required)required @endif @include('components.field-error-attributes', ['field'=>$field,'id'=>$field])>
    @include('components.field-error', ['field'=>$field,'id'=>$field])
</div>
@endforeach
<div><label for="civil_status">Civil status</label><select id="civil_status" name="civil_status" @include('components.field-error-attributes', ['field'=>'civil_status','id'=>'civil_status'])><option value="">Not yet recorded</option>@foreach(['single'=>'Single','married'=>'Married','widowed'=>'Widowed','separated'=>'Separated','other'=>'Other — requires contract review'] as $value=>$label)<option value="{{ $value }}" @if(old('civil_status',$record->civil_status)===$value)selected @endif>{{ $label }}</option>@endforeach</select>@include('components.field-error', ['field'=>'civil_status','id'=>'civil_status'])</div>
<div><label for="gender">Gender</label><select id="gender" name="gender" @include('components.field-error-attributes', ['field'=>'gender','id'=>'gender'])><option value="">Not recorded</option>@foreach(config('tenants.genders') as $value=>$label)<option value="{{ $value }}" @if(old('gender',$record->gender)===$value)selected @endif>{{ $label }}</option>@endforeach</select>@include('components.field-error', ['field'=>'gender','id'=>'gender'])</div>
@foreach(['citizenship'=>'Citizenship','phone'=>'Contact number','email'=>'Email'] as $field=>$label)<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $field==='email' ? 'email' : ($field==='phone' ? 'tel' : 'text') }}" value="{{ old($field,$record->$field) }}" maxlength="{{ ['phone'=>40,'email'=>255,'citizenship'=>100][$field] }}" @include('components.field-error-attributes', ['field'=>$field,'id'=>$field])>@include('components.field-error', ['field'=>$field,'id'=>$field])</div>@endforeach
@unless($registration)<div><label for="status">Tenant status</label><select id="status" name="status" @include('components.field-error-attributes', ['field'=>'status','id'=>'status'])>@foreach(['active'=>'Active','inactive'=>'Inactive','archived'=>'Archived'] as $value=>$label)<option value="{{ $value }}" @if(old('status',$record->status)===$value)selected @endif>{{ $label }}</option>@endforeach</select>@include('components.field-error', ['field'=>'status','id'=>'status'])</div>@endunless
<div class="md:col-span-2"><label for="address">Permanent residential address</label><textarea id="address" name="address" rows="3" maxlength="2000" @include('components.field-error-attributes', ['field'=>'address','id'=>'address'])>{{ old('address',$record->address) }}</textarea>@include('components.field-error', ['field'=>'address','id'=>'address'])</div>
<div class="md:col-span-2"><label for="remarks">Remarks</label><textarea id="remarks" name="remarks" rows="2" maxlength="2000" @include('components.field-error-attributes', ['field'=>'remarks','id'=>'remarks'])>{{ old('remarks',$record->remarks) }}</textarea>@include('components.field-error', ['field'=>'remarks','id'=>'remarks'])</div></div>
<p class="text-sm text-slate-600 mt-4">Name format: LAST NAME, FIRST NAME, M.I. A middle initial is derived when left blank. Contract issuance requires a verified address, contact number and civil status.</p></section>
<section class="panel mt-5" id="identification"><h2>Account and identification</h2><div class="grid md:grid-cols-2 gap-x-6 gap-y-2">
@foreach(['previous_account_number'=>'Previous account number','present_account_number'=>'Present account number','government_id_type'=>'Government-issued ID type','government_id_place_issued'=>'ID place issued'] as $field=>$label)<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$record->$field) }}" maxlength="{{ $field==='government_id_place_issued' ? 255 : ($field==='government_id_type' ? 100 : 120) }}" @include('components.field-error-attributes', ['field'=>$field,'id'=>$field])>@include('components.field-error', ['field'=>$field,'id'=>$field])</div>@endforeach
<div><label for="government_id_number">Government-issued ID number</label><input id="government_id_number" name="government_id_number" type="password" autocomplete="new-password" maxlength="120"  @include('components.field-error-attributes', ['field'=>'government_id_number','id'=>'government_id_number','hint'=>'id-help'])>@include('components.field-error', ['field'=>'government_id_number','id'=>'government_id_number','hint'=>'id-help'])<p class="text-xs text-slate-600 mt-2" id="id-help">{{ $record->masked_government_id_number ? 'Recorded: '.$record->masked_government_id_number.'. Leave blank to retain it; enter a value to replace it.' : 'Stored encrypted. Full numbers are restricted to authorized viewers.' }}</p></div>
<div><label for="government_id_date_issued">ID date issued</label><input id="government_id_date_issued" type="date" name="government_id_date_issued" max="{{ today()->toDateString() }}" value="{{ old('government_id_date_issued',$record->government_id_date_issued) }}" @include('components.field-error-attributes', ['field'=>'government_id_date_issued','id'=>'government_id_date_issued'])>@include('components.field-error', ['field'=>'government_id_date_issued','id'=>'government_id_date_issued'])</div>
<div class="md:col-span-2"><label for="government_id_issuance_exception_reason">Reason the ID has no issuance date or place</label><textarea id="government_id_issuance_exception_reason" name="government_id_issuance_exception_reason" rows="2" maxlength="2000" @include('components.field-error-attributes', ['field'=>'government_id_issuance_exception_reason','id'=>'government_id_issuance_exception_reason'])>{{ old('government_id_issuance_exception_reason',$record->government_id_issuance_exception_reason) }}</textarea>@include('components.field-error', ['field'=>'government_id_issuance_exception_reason','id'=>'government_id_issuance_exception_reason'])<p class="text-xs text-slate-600 mt-2">Record the verified reason only. Contract approval remains required; do not invent issuance details.</p></div>
</div><p class="text-sm text-slate-600 mt-4">Account numbers remain text, including leading zeroes. An account number does not identify a tenant uniquely.</p></section>
@if($registration)
    <section class="panel mt-5 scroll-mt-4" id="business">
        <h2>Business information</h2>
        <p class="text-sm text-slate-600 mt-2">Add a business for each authorized trade. Individual stallholders can leave the commercial name blank.</p>
        <p class="text-sm text-slate-600 mt-2">Verify the line of business and permitted goods before confirming a stall. Later trade changes require an authorized renewal to appear in a new lease.</p>
        <div class="mt-4" data-businesses>
            @foreach($businesses as $businessKey=>$values)
                @include('tenants.registration-business', compact('businessKey','values'))
            @endforeach
        </div>
        <template data-business-template>@include('tenants.registration-business', ['businessKey'=>'__BUSINESS__','values'=>[],'errorApplies'=>false])</template>
        <button type="button" class="btn secondary mt-2" data-add-business hidden>Add business</button>
    </section>
    <section class="panel mt-5 scroll-mt-4" id="stall">
        <h2>Stall selections</h2>
        <p class="text-sm text-slate-600 mt-2">Choose an existing stall for each business you want to assign. A draft keeps the choices without reserving occupancy.</p>
        @if($stalls->isEmpty())
            <p class="text-sm text-slate-600 mt-3">No eligible stalls are available. You can save the tenant and businesses now.</p>
        @endif
        <div class="mt-4" data-assignments>
            @foreach($pending as $assignmentKey=>$selection)
                @include('tenants.registration-assignment', compact('assignmentKey','selection'))
            @endforeach
        </div>
        <template data-assignment-template>@include('tenants.registration-assignment', ['assignmentKey'=>'__ASSIGNMENT__','selection'=>[],'errorApplies'=>false])</template>
        @if($stalls->isNotEmpty())
            <button type="button" class="btn secondary mt-2" data-add-assignment hidden>Add stall selection</button>
            <span class="text-sm text-slate-600 ml-2" data-add-assignment-help></span>
        @endif
    </section>
    <section class="panel mt-5 scroll-mt-4" id="rental">
        <h2>Rental preview</h2>
        @unless(config('billing.fixed_day_enabled'))
            @can('rentals.override.request')<p class="text-sm text-slate-600 mt-2">After confirming a stall assignment, open Rental Information on the tenant record to request a manual override.</p>@endcan
        @endunless
        @can('assignments.manage')
            <p class="text-sm text-slate-600 mt-2">The approved business-category rate uses the minimum for Inner and maximum for Outer.
                @if(config('billing.fixed_day_enabled'))
                    Confirming rechecks the approved rate, interval and stall availability. Additional concurrent occupancy requires separate approval.
                @else
                    The server checks the rate and market policy again when you confirm.
                @endif
            </p>
            <p class="text-sm text-slate-600 mt-3" data-preview-empty>Choose a business, stall and start date to preview the rental.</p>
            <div class="mt-3" aria-live="polite" data-previews></div>
        @else
            <p class="text-sm text-slate-600 mt-2">An assignment officer can preview rent and confirm selected stalls.</p>
        @endcan
    </section>
    <section class="panel mt-5 scroll-mt-4" id="helpers">
        <h2>Authorized helpers</h2>
        <p class="text-sm text-slate-600 mt-2">Each selected stall can have up to two helpers. Their registrations take effect when the assignment is confirmed.</p>
        <div class="mt-3" data-pending-helpers>
            @foreach($pending as $assignmentKey=>$selection)
                @include('tenants.registration-helpers', compact('assignmentKey','selection'))
            @endforeach
        </div>
        <template data-helpers-template>@include('tenants.registration-helpers', ['assignmentKey'=>'__ASSIGNMENT__','selection'=>[],'errorApplies'=>false])</template>
    </section>
@else
    <section class="panel mt-5 scroll-mt-4" id="helpers">
        <h2>Authorized helpers</h2>
        @if($helperAssignment)
            <input type="hidden" name="helper_assignment_id" value="{{ $helperAssignment->id }}">
            <p class="text-sm text-slate-600 mt-2">Editing helpers for {{ $helperAssignment->stall->code }} · {{ $helperAssignment->start_date }} · {{ $helperAssignment->status }}.</p>
            <div class="flex flex-wrap gap-3 mt-4">
                @foreach($assignments as $assignment)
                    <a class="text-sm text-blue-800 underline" href="{{ route('records.edit',['tenants',$record->id,'assignment_id'=>$assignment->id]) }}#helpers">{{ $assignment->stall->code }} · {{ $assignment->start_date }}</a>
                @endforeach
            </div>
            @include('tenants.helper-fields', ['key'=>'existing-helper','prefix'=>'helpers','helperValues'=>old('helpers', [$helpers->get(1),$helpers->get(2)])])
        @else
            <p class="text-sm text-slate-600 mt-2">Register helpers after assigning a stall.</p>
        @endif
    </section>
@endif
<section class="panel mt-5 scroll-mt-4" id="review">
    <h2>{{ $registration ? 'Review and confirm' : 'Save changes' }}</h2>
    @if($registration)
        <div class="text-sm text-slate-700 space-y-1 mt-3" data-review></div>
        <p class="text-sm text-slate-600 mt-3">Saving a draft keeps pending choices. Confirmation creates the tenant record and any authorized assignments. Lease preparation and approval happen separately.</p>
        @cannot('assignments.manage')
            <p class="notice mt-3" data-confirm-restriction @if(empty($pending))hidden @endif>Save pending stall selections as a draft for an assignment officer to confirm.</p>
        @endcannot
    @endif
    <div class="flex flex-wrap gap-3 mt-4">
        @if($registration)
            <button class="btn secondary" type="submit" name="registration_action" value="draft">Save draft</button>
            <button class="btn" type="submit" name="registration_action" value="confirm" data-confirm-registration @if(count($pending) && !auth()->user()->can('assignments.manage')) disabled @endif>Confirm registration</button>
        @else
            <button class="btn" type="submit">Save tenant</button>
        @endif
        <a class="btn secondary" href="{{ $record->exists ? route('records.show',['tenants',$record->id]) : route('records.index','tenants') }}">Cancel</a>
    </div>
</section>
</form>
@endsection
