@extends('layouts.app')
@section('title',$record->exists ? 'Edit section' : 'Add section')
@section('content')
<form class="panel max-w-3xl" method="post" action="{{ $record->exists ? route('records.update',['sections',$record->id]) : route('records.store','sections') }}">
    @csrf
    @if($record->exists) @method('PUT') @endif
    @if($fromStall)
        <input type="hidden" name="from_stall" value="1">
        <p class="notice">Your stall form is still open in the other tab. After saving the section, return there and refresh the section choices.</p>
    @endif
    <div class="grid md:grid-cols-2 gap-x-6 gap-y-2">
        <div><label for="market_id">Market</label><select name="market_id" id="market_id" required @include('components.field-error-attributes', ['field'=>'market_id','id'=>'market_id'])>
            <option value="">Choose a market</option>
            @foreach($markets as $market)
                <option value="{{ $market->id }}" @if(old('market_id',$record->market_id)==$market->id) selected @endif>{{ $market->name }}</option>
            @endforeach
        </select>@include('components.field-error', ['field'=>'market_id','id'=>'market_id'])</div>
        <div><label for="code">Section code</label><input name="code" id="code" value="{{ old('code',$record->code) }}" maxlength="40" required @include('components.field-error-attributes', ['field'=>'code','id'=>'code'])>@include('components.field-error', ['field'=>'code','id'=>'code'])</div>
        <div class="md:col-span-2"><label for="name">Section name</label><input name="name" id="name" value="{{ old('name',$record->name) }}" maxlength="255" required @include('components.field-error-attributes', ['field'=>'name','id'=>'name'])>@include('components.field-error', ['field'=>'name','id'=>'name'])</div>
        <div class="md:col-span-2"><label for="description">Description <span class="text-slate-600 font-normal">(optional)</span></label>
            <textarea name="description" id="description" rows="3" maxlength="2000" @include('components.field-error-attributes', ['field'=>'description','id'=>'description'])>{{ old('description',$record->description) }}</textarea>@include('components.field-error', ['field'=>'description','id'=>'description'])</div>
        <div><label for="color">Map label color <span class="text-slate-600 font-normal">(optional)</span></label>
            <input name="color" id="color" value="{{ old('color',$record->color) }}" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#2366AC"  @include('components.field-error-attributes', ['field'=>'color','id'=>'color','hint'=>'section-color-format'])>@include('components.field-error', ['field'=>'color','id'=>'color','hint'=>'section-color-format'])
            <p id="section-color-format" class="text-xs text-slate-600 mt-1">Use # followed by six hexadecimal characters.</p>
        </div>
        <div><label for="sort_order">Display order</label><input name="sort_order" id="sort_order" type="number" min="0" max="999999" step="1" value="{{ old('sort_order',$record->sort_order ?? 0) }}" required @include('components.field-error-attributes', ['field'=>'sort_order','id'=>'sort_order'])>@include('components.field-error', ['field'=>'sort_order','id'=>'sort_order'])</div>
        <div><label for="status">Status</label><select name="status" id="status" required  @include('components.field-error-attributes', ['field'=>'status','id'=>'status','hint'=>'section-status-help'])>
            @foreach(['active','inactive'] as $status)
                <option value="{{ $status }}" @if(old('status',$record->status ?? 'active')===$status) selected @endif>{{ ucfirst($status) }}</option>
            @endforeach
        </select>@include('components.field-error', ['field'=>'status','id'=>'status','hint'=>'section-status-help'])<p id="section-status-help" class="text-xs text-slate-600 mt-1">Inactive sections retain existing stalls and are unavailable for new placement or assignments.</p></div>
    </div>
    <div class="flex flex-wrap gap-3 mt-6">
        <button class="btn">Save section</button>
        <a class="btn secondary" href="{{ route('records.index',['type'=>'sections','market_id'=>$record->market_id]) }}">Cancel</a>
    </div>
</form>
@endsection
