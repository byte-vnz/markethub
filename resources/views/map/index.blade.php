@extends('layouts.app')
@section('title','Interactive Market Map')
@section('subtitle','Select a stall to inspect occupancy, tenant and authorized billing information.')
@section('actions')
    @can('markets.manage')
        @if($market)<a class="btn secondary" href="{{ route('map.editor',$market) }}">Edit layout</a>@endif
    @endcan
@endsection
@section('content')
<form class="toolbar mb-6" method="get">
    <div><label for="market">Market</label><select name="market_id" id="market" data-clear-section-filter="section">
        @foreach($markets as $choice)<option value="{{ $choice->id }}" @if($market && $market->id === $choice->id) selected @endif>{{ $choice->name }}</option>@endforeach
    </select></div>
    <div><label for="section">Section</label><select name="section_id" id="section">
        <option value="">All sections</option>
        @foreach($sections as $section)
            @if($section->status === 'active' || optional($selectedSection)->id === $section->id)
                <option value="{{ $section->id }}" @if(optional($selectedSection)->id === $section->id) selected @endif>{{ $section->name }}@if($section->status === 'inactive') (Inactive)@endif</option>
            @endif
        @endforeach
    </select></div>
    <div><label for="status">Occupancy</label><select id="status" name="status">
        <option value="">All statuses</option>
        @foreach(\App\Models\MarketSection::STALL_STATUSES as $status)
            <option value="{{ $status }}" @if(request('status') === $status) selected @endif>{{ ucfirst($status) }}</option>
        @endforeach
    </select></div>
    <div><label for="location_type">Stall location</label><select id="location_type" name="location_type"><option value="">All locations</option>
        @foreach(['inner'=>'Inner','outer'=>'Outer','unclassified'=>'Unclassified'] as $value=>$label)
            <option value="{{ $value }}" @if(request('location_type') === $value) selected @endif>{{ $label }}</option>
        @endforeach
    </select></div>
    @can('tenants.view')
        <div><label for="business_category_id">Business category</label><select id="business_category_id" name="business_category_id"><option value="">All categories</option>
            @foreach($businessCategories as $category)<option value="{{ $category->id }}" @if(request('business_category_id') == $category->id) selected @endif>{{ $category->name }}</option>@endforeach
        </select></div>
    @endcan
    @if($canSeeComputation)
        <div><label for="map-period">Rental reference month</label><input id="map-period" name="period" type="month" value="{{ $period }}"></div>
        <div><label for="computation_mode">Rental computation</label><select id="computation_mode" name="computation_mode">
            <option value="">All</option>
            <option value="automatic" @if(request('computation_mode') === 'automatic') selected @endif>Automatic</option>
            <option value="manual_override" @if(request('computation_mode') === 'manual_override') selected @endif>Approved manual override</option>
        </select></div>
    @endif
    <button class="btn">Apply filters</button>
</form>
<div class="grid xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,1fr)] gap-5 items-start">
    <section class="panel min-w-0">
        <h2 class="mb-4">{{ $market ? $market->name : 'No market registered' }}</h2>
        <div class="overflow-x-auto pb-4" role="region" aria-label="Stall layout" tabindex="0">
            <div class="grid gap-3" style="grid-template-columns:repeat({{ $layout ? $layout->columns : 6 }},minmax(76px,1fr));min-width:{{ ($layout ? $layout->columns : 6)*88 }}px">
                @php($canViewStalls = auth()->user()->can('stalls.view'))
                @forelse($stalls as $stall)
                    @php($tile = $canViewStalls ? 'a' : 'div')
                    <{{ $tile }} class="stall status-{{ $stall->status }}"
                            @if($canViewStalls) href="{{ route('records.show',['stalls',$stall->id]) }}" data-stall-url="{{ route('map.details',['stall'=>$stall] + ($canSeeComputation ? ['period'=>$period] : [])) }}" @endif
                            @if($stall->position) style="grid-column:{{ $stall->position->column }} / span {{ $stall->position->width }};grid-row:{{ $stall->position->row }} / span {{ $stall->position->height }}" @endif>
                        <strong class="block text-sm">{{ $stall->code }}</strong>
                        <span class="block text-xs mt-2">{{ ucfirst($stall->status) }}</span>
                        <span class="flex justify-center items-center gap-1 mt-1 text-xs break-all">
                            @include('sections.swatch',['section'=>$stall->section]){{ optional($stall->section)->name ?? 'Unknown section' }}
                        </span>
                    </{{ $tile }}>
                @empty
                    <p class="empty col-span-full">No stalls match this view.</p>
                @endforelse
            </div>
        </div>
        <div class="flex flex-wrap gap-2 border-t pt-4" aria-label="Occupancy legend">
            @foreach(\App\Models\MarketSection::STALL_STATUSES as $status)
                <span class="badge status-{{ $status }}">{{ ucfirst($status) }}</span>
            @endforeach
        </div>
        @if($sections->isNotEmpty())
            <h3 class="mt-5 mb-2">Section summary</h3>
            <ul class="divide-y divide-slate-200 text-sm">
                @foreach($selectedSection ? collect([$selectedSection]) : $sections as $section)
                    @php($visible = $sectionStalls->get($section->id, collect()))
                    @php($counts = $visible->countBy('status'))
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                        <span class="inline-flex items-center gap-2 font-medium">@include('sections.swatch',['section'=>$section]){{ $section->name }}@if($section->status === 'inactive') <span class="badge status-inactive">Inactive</span>@endif</span>
                        <span class="text-slate-600">{{ $visible->count() }} shown · {{ $counts->get('occupied', 0) }} occupied · {{ $counts->get('vacant', 0) }} vacant
                            @foreach(['reserved','maintenance','inactive'] as $status)@if($counts->has($status)) · {{ $counts->get($status) }} {{ $status }}@endif @endforeach
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    <div class="xl:sticky xl:top-5 min-w-0">
        <p id="stall-detail-status" class="sr-only" role="status"></p>
        <section id="stall-detail" class="panel" aria-label="Selected stall details">
            <h2>Stall details</h2>
            <p class="empty">{{ $canViewStalls ? 'Select a stall to view its record.' : 'Your account can view stall locations and occupancy status.' }}</p>
        </section>
    </div>
</div>
@endsection
