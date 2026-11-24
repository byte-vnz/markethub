@extends('layouts.app')
@section('title',($record->exists?'Edit ':'Add ').\Illuminate\Support\Str::singular($spec['label']))
@section('content')
<form class="panel max-w-4xl" method="post" action="{{ $record->exists ? route('records.update', [$type,$record->id]) : route('records.store',$type) }}">
    @csrf
    @if($record->exists)@method('PUT')@endif
    <div class="grid md:grid-cols-2 gap-x-6 gap-y-2">
        @foreach($spec['fields'] as $key=>$meta)
            @php
                $value = old($key, $record->$key);
                $value = is_scalar($value) || $value === null ? $value : $record->$key;
                $occupied = $type === 'stalls' && $key === 'status' && $record->status === 'occupied';
                $required = !$occupied && ($meta[1] === 'select' || in_array($key, ['name','code','reference','classification','approval_reference','effective_from','market_id','market_section_id','tenant_id','area','amount'], true));
                if ($key === 'location_type') {
                    $required = !$record->exists;
                }
                $fieldError = ['field'=>$key,'id'=>$key,'hint'=>$key === 'location_type' ? 'stall-location-help' : null];
            @endphp
            <div @if($meta[1]==='textarea')class="md:col-span-2"@endif>
                <label @unless($occupied)for="{{ $key }}"@endunless>{{ $meta[0] }}@if($required) (required)@endif</label>
                @if($occupied)
                    <input type="hidden" name="status" value="occupied">
                    <p class="notice">Occupied. End or transfer the assignment to change occupancy.</p>
                @elseif($meta[1]==='textarea')
                    <textarea id="{{ $key }}" name="{{ $key }}" rows="3" maxlength="2000" @include('components.field-error-attributes', $fieldError)>{{ $value }}</textarea>
                @elseif($meta[1]==='select')
                    <select id="{{ $key }}" name="{{ $key }}" @if($required)required @endif @include('components.field-error-attributes', $fieldError)>
                        @foreach($meta[2] as $v=>$label)
                            <option value="{{ $v }}" @if($value===$v)selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($key==='location_type')
                        <p id="stall-location-help" class="text-xs text-slate-600 mt-1">Inner uses the business category minimum rate; Outer uses its maximum. Unclassified existing stalls cannot receive new authoritative assessments.</p>
                    @endif
                @elseif($type==='stalls' && $key==='market_section_id')
                    @include('stalls.section-picker',['selectedSectionId'=>$value])
                @elseif($meta[1]==='relation')
                    <select id="{{ $key }}" name="{{ $key }}" @if($required)required @endif @include('components.field-error-attributes', $fieldError)>
                        <option value="">Choose {{ strtolower($meta[0]) }}</option>
                        @foreach($options[$key] as $option)
                            <option value="{{ $option->id }}" @if($value==$option->id)selected @endif>{{ $option->name }} @if($option->code) · {{ $option->code }}@endif</option>
                        @endforeach
                    </select>
                @else
                    <input id="{{ $key }}" name="{{ $key }}" type="{{ $meta[1] }}" value="{{ $value }}"
                        @if($meta[1]==='number')step="0.01" min="{{ $key==='amount' ? '0.01' : '0' }}" max="{{ $key==='amount' ? '999999999999.99' : '99999999.99' }}" @endif
                        @if($meta[1]==='text')maxlength="{{ ['name'=>120,'code'=>40,'classification'=>120,'approval_reference'=>120][$key] ?? 255 }}" @endif
                        @if($required)required @endif @include('components.field-error-attributes', $fieldError)>
                @endif
                @unless($occupied)@include('components.field-error', $fieldError)@endunless
            </div>
        @endforeach
    </div>
    @if($type==='stalls' && $record->exists)
        <div class="mt-4">
            <label for="change_reason">Reason for area or location correction</label>
            <textarea id="change_reason" name="change_reason" maxlength="1000" @include('components.field-error-attributes', ['field'=>'change_reason','id'=>'change_reason','hint'=>'change-reason-help'])>{{ is_scalar(old('change_reason')) ? old('change_reason') : '' }}</textarea>
            @include('components.field-error', ['field'=>'change_reason','id'=>'change_reason'])
            <p id="change-reason-help" class="text-xs text-slate-600 mt-1">Required when changing area or location on a stall with assignment history. Existing assessments retain their original values.</p>
        </div>
    @endif
    <div class="flex flex-wrap gap-3 mt-6">
        <button class="btn" type="submit">Save record</button>
        <a class="btn secondary" href="{{ route('records.index',$type) }}">Cancel</a>
    </div>
    <p class="text-xs text-slate-600 mt-4">Use inactive status to archive eligible records. History is retained. Select active/vacant to restore an eligible record.</p>
</form>
@endsection
