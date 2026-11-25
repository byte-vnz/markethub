<fieldset class="border-t border-slate-200 py-4" data-registration-assignment="{{ $assignmentKey }}">
    <legend class="font-semibold text-sm" data-assignment-heading>Stall selection</legend>
    <div class="grid md:grid-cols-2 gap-x-6 gap-y-3">
        <div>
            <label for="stall-{{ $assignmentKey }}-business">Business for this stall</label>
            <select id="stall-{{ $assignmentKey }}-business" name="pending_assignments[{{ $assignmentKey }}][business_key]" data-linked-business required @include('components.field-error-attributes', ['field'=>'pending_assignments['.$assignmentKey.'][business_key]','id'=>'stall-'.$assignmentKey.'-business'])>
                <option value="">Choose a business</option>
                @foreach($businesses as $key=>$business)
                    <option value="{{ $key }}" @if(($selection['business_key'] ?? null) == $key)selected @endif>{{ $business['name'] ?? 'Individual stallholder' }}</option>
                @endforeach
            </select>@include('components.field-error', ['field'=>'pending_assignments['.$assignmentKey.'][business_key]','id'=>'stall-'.$assignmentKey.'-business'])
        </div>
        <div>
            <label for="stall-{{ $assignmentKey }}">Existing stall</label>
            <select id="stall-{{ $assignmentKey }}" name="pending_assignments[{{ $assignmentKey }}][stall_id]" data-assignment-stall required @include('components.field-error-attributes', ['field'=>'pending_assignments['.$assignmentKey.'][stall_id]','id'=>'stall-'.$assignmentKey])>
                <option value="">Choose a stall</option>
                @foreach($stalls as $stall)
                    <option value="{{ $stall->id }}" @if(($selection['stall_id'] ?? null) == $stall->id)selected @endif data-label="{{ $stall->market->name }} · {{ $stall->code }}" data-area="{{ $stall->area }}" data-location="{{ $stall->location_type }}">{{ $stall->market->name }} · {{ $stall->section->name }} · {{ $stall->code }} · {{ ucfirst($stall->status) }}</option>
                @endforeach
            </select>@include('components.field-error', ['field'=>'pending_assignments['.$assignmentKey.'][stall_id]','id'=>'stall-'.$assignmentKey])
            <label class="sr-only" for="stall-{{ $assignmentKey }}-search">Filter stalls</label>
            <input type="search" class="mt-2" id="stall-{{ $assignmentKey }}-search" placeholder="Filter by market, section or stall code" data-option-search="stall-{{ $assignmentKey }}" maxlength="100">
            <p class="text-sm text-slate-600 mt-2" data-stall-basis></p>
        </div>
        <div><label for="stall-{{ $assignmentKey }}-start">Start date</label><input type="date" id="stall-{{ $assignmentKey }}-start" name="pending_assignments[{{ $assignmentKey }}][start_date]" value="{{ $selection['start_date'] ?? today()->toDateString() }}" max="{{ today()->toDateString() }}" data-assignment-start required @include('components.field-error-attributes', ['field'=>'pending_assignments['.$assignmentKey.'][start_date]','id'=>'stall-'.$assignmentKey.'-start'])>@include('components.field-error', ['field'=>'pending_assignments['.$assignmentKey.'][start_date]','id'=>'stall-'.$assignmentKey.'-start'])</div>
        <div><label for="stall-{{ $assignmentKey }}-end">End date <span class="text-slate-500 font-normal">(optional)</span></label><input type="date" id="stall-{{ $assignmentKey }}-end" name="pending_assignments[{{ $assignmentKey }}][end_date]" value="{{ $selection['end_date'] ?? '' }}" data-assignment-end @include('components.field-error-attributes', ['field'=>'pending_assignments['.$assignmentKey.'][end_date]','id'=>'stall-'.$assignmentKey.'-end'])>@include('components.field-error', ['field'=>'pending_assignments['.$assignmentKey.'][end_date]','id'=>'stall-'.$assignmentKey.'-end'])</div>
        @include('assignments.billing-terms', ['billingFieldPrefix'=>'pending_assignments['.$assignmentKey.']','billingIdPrefix'=>'stall-'.$assignmentKey])
    </div>
    <details class="mt-3">
        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Concurrent assignment authorization</summary>
        <div class="grid md:grid-cols-2 gap-3 mt-3">
            <div><label for="stall-{{ $assignmentKey }}-auth">Authorization reference</label><input id="stall-{{ $assignmentKey }}-auth" name="pending_assignments[{{ $assignmentKey }}][concurrent_authorization_reference]" value="{{ $selection['concurrent_authorization_reference'] ?? '' }}" maxlength="255" @include('components.field-error-attributes', ['field'=>'pending_assignments['.$assignmentKey.'][concurrent_authorization_reference]','id'=>'stall-'.$assignmentKey.'-auth'])>@include('components.field-error', ['field'=>'pending_assignments['.$assignmentKey.'][concurrent_authorization_reference]','id'=>'stall-'.$assignmentKey.'-auth'])</div>
            <div><label for="stall-{{ $assignmentKey }}-reason">Authorization reason</label><textarea id="stall-{{ $assignmentKey }}-reason" name="pending_assignments[{{ $assignmentKey }}][concurrent_authorization_reason]" rows="2" maxlength="2000" @include('components.field-error-attributes', ['field'=>'pending_assignments['.$assignmentKey.'][concurrent_authorization_reason]','id'=>'stall-'.$assignmentKey.'-reason'])>{{ $selection['concurrent_authorization_reason'] ?? '' }}</textarea>@include('components.field-error', ['field'=>'pending_assignments['.$assignmentKey.'][concurrent_authorization_reason]','id'=>'stall-'.$assignmentKey.'-reason'])</div>
        </div>
    </details>
    <button type="button" class="btn secondary mt-3" data-remove-assignment>Remove stall selection</button>
</fieldset>
