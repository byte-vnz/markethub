@extends('layouts.app')
@section('title',$record->name)
@section('subtitle',$record->market->name.' · '.$record->code)
@section('actions')
    @can('update',$record)<a class="btn" href="{{ route('records.edit',['sections',$record->id]) }}">Edit section</a>@endcan
@endsection
@section('content')
<section class="panel max-w-3xl">
    <h2>Section details</h2>
    <dl>
        <div class="detailrow"><dt>Status</dt><dd>{{ ucfirst($record->status) }}</dd></div>
        <div class="detailrow"><dt>Display order</dt><dd>{{ $record->sort_order }}</dd></div>
        <div class="detailrow"><dt>Map label color</dt><dd class="flex items-center gap-2">@include('sections.swatch',['section'=>$record]){{ $record->color ?: 'None' }}</dd></div>
        @if($record->description !== null && $record->description !== '')<div class="detailrow"><dt>Description</dt><dd class="whitespace-pre-line">{{ $record->description }}</dd></div>@endif
    </dl>
    <h2 class="mt-6">Stalls in this section</h2>
    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 mt-2">
        <div class="detailrow"><dt>Total</dt><dd>{{ $record->stalls_count }}</dd></div>
        @foreach(\App\Models\MarketSection::STALL_STATUSES as $status)
            <div class="detailrow"><dt>{{ ucfirst($status) }}</dt><dd>{{ $record->{$status.'_count'} }}</dd></div>
        @endforeach
    </dl>
    <div class="flex flex-wrap gap-3 mt-6">
        @can('stalls.view')<a class="btn secondary" href="{{ route('records.index',['type'=>'stalls','market_id'=>$record->market_id,'section_id'=>$record->id]) }}">View stalls</a>@endcan
        @can('map.view')<a class="btn secondary" href="{{ route('map',['market_id'=>$record->market_id,'section_id'=>$record->id]) }}">View on market map</a>@endcan
        <a class="btn secondary" href="{{ route('records.index',['type'=>'sections','market_id'=>$record->market_id]) }}">Back to sections</a>
    </div>
</section>
@endsection
