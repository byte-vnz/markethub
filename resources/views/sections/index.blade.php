@extends('layouts.app')
@section('title','Market Sections')
@section('actions')
    @can('sections.manage')
        <a class="btn" href="{{ route('records.create', ['type'=>'sections','market_id'=>$filters['market_id'] ?? null]) }}">Add section</a>
    @endcan
@endsection
@section('content')
<section class="panel">
    <form class="toolbar mb-5" method="get">
        <div><label for="q">Section name or code</label><input id="q" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}"></div>
        <div><label for="market_id">Market</label><select id="market_id" name="market_id">
            <option value="">All markets</option>
            @foreach($markets as $market)
                <option value="{{ $market->id }}" @if(($filters['market_id'] ?? null) == $market->id) selected @endif>{{ $market->name }}</option>
            @endforeach
        </select></div>
        <div><label for="status">Status</label><select id="status" name="status">
            <option value="">All statuses</option>
            @foreach(['active','inactive'] as $status)
                <option value="{{ $status }}" @if(($filters['status'] ?? '') === $status) selected @endif>{{ ucfirst($status) }}</option>
            @endforeach
        </select></div>
        <button class="btn secondary">Apply filters</button>
        <a class="text-sm underline py-3" href="{{ route('records.index','sections') }}">Clear</a>
    </form>
    <div class="tablewrap" role="region" aria-label="Market sections" tabindex="0">
        <table>
            <thead><tr><th scope="col">Section</th><th scope="col">Market</th><th scope="col">Status</th><th scope="col">Order</th><th scope="col">Stalls</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    <td><div class="flex items-center gap-2">@include('sections.swatch',['section'=>$record])<strong>{{ $record->name }}</strong></div>
                        <div class="text-xs text-slate-600">{{ $record->code }}</div>
                        @if($record->description !== null && $record->description !== '')
                            <p class="mt-1 max-w-md whitespace-pre-line break-words text-xs text-slate-600">{{ $record->description }}</p>
                        @endif
                    </td>
                    <td>{{ $record->market->name }}</td>
                    <td><span class="badge @if($record->status === 'inactive') status-inactive @endif">{{ ucfirst($record->status) }}</span></td>
                    <td>{{ $record->sort_order }}</td>
                    <td><strong>{{ $record->stalls_count }} total</strong><div class="text-xs text-slate-600">
                        @foreach(\App\Models\MarketSection::STALL_STATUSES as $status)
                            @if(!$loop->first) · @endif{{ $record->{$status.'_count'} }} {{ $status }}
                        @endforeach
                    </div></td>
                    <td><div class="flex flex-wrap gap-3">
                        <a class="text-blue-800 underline" href="{{ route('records.show',['sections',$record->id]) }}">View<span class="sr-only"> {{ $record->name }}</span></a>
                        @can('update',$record)<a class="text-blue-800 underline" href="{{ route('records.edit',['sections',$record->id]) }}">Edit<span class="sr-only"> {{ $record->name }}</span></a>@endcan
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No sections match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $records->links() }}</div>
</section>
@can('sections.manage')
    @php($reorderLimit = \App\Services\SectionService::MAX_REORDER_SECTIONS)
    @if(isset($filters['market_id']) && $orderable->count() > 1 && $orderable->count() <= $reorderLimit)
        <details class="panel mt-5" data-section-order hidden @if($errors->has('ids') || $errors->has('ids.*')) open @endif>
            <summary class="cursor-pointer font-semibold">Reorder sections for {{ $markets->firstWhere('id', (int) $filters['market_id'])->name }}</summary>
            <form method="post" action="{{ route('sections.order') }}" class="mt-4 max-w-3xl">
                @csrf
                <input type="hidden" name="market_id" value="{{ $filters['market_id'] }}">
                <ol data-section-order-list class="divide-y divide-slate-200">
                    @foreach($orderable as $section)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <input type="hidden" name="ids[]" value="{{ $section->id }}">
                            <span>{{ $section->name }} · {{ $section->code }}</span>
                            <div class="flex gap-2">
                                <button type="button" class="btn secondary" data-order-move="up" aria-label="Move {{ $section->name }} up">Move up</button>
                                <button type="button" class="btn secondary" data-order-move="down" aria-label="Move {{ $section->name }} down">Move down</button>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="text-sm text-slate-600 mt-3" aria-live="polite" data-section-order-status></p>
                <button class="btn mt-3">Save order</button>
            </form>
        </details>
    @elseif($orderable->count() > $reorderLimit)
        <p class="text-sm text-slate-600 mt-4">This market has more than {{ $reorderLimit }} sections. Edit Display order on individual sections.</p>
    @elseif(empty($filters['market_id']) && $records->isNotEmpty())
        <p class="text-sm text-slate-600 mt-4">To reorder sections, filter by one market.</p>
    @endif
@endcan
@endsection
