@extends('layouts.app')
@section('title','Rental Billing')
@section('subtitle','Review draft bills before explicit posting. Posted amounts remain immutable.')
@section('actions')@can('billing.generate')<a class="btn" href="{{ route('billing.create') }}">Generate draft bills</a>@endcan @endsection
@section('content')
<section class="panel">
    <form class="toolbar mb-5" method="get" action="{{ route('billing.index') }}" aria-label="Filter rental bills">
        @if(!empty($tenantId))<input type="hidden" name="tenant_id" value="{{ $tenantId }}">@endif
        <div>
            <label for="period">{{ $showFixedFilter ? 'Period start month' : 'Billing month' }}</label>
            <input id="period" type="month" name="period" value="{{ request('period') }}">
        </div>
        @if($showFixedFilter)
            <div>
                <label for="period-start">Fixed-day period start</label>
                <input id="period-start" type="date" name="period_start" value="{{ request('period_start') }}" aria-describedby="filter-period-hint">
                <p id="filter-period-hint" class="text-sm text-slate-600 mt-1">Exact date takes precedence over the month.</p>
            </div>
        @endif
        <div>
            <label for="status">Posting status</label>
            <select id="status" name="status"><option value="">All</option><option @if(request('status')==='draft')selected @endif>draft</option><option @if(request('status')==='posted')selected @endif>posted</option></select>
        </div>
        <button class="btn secondary" type="submit">Filter</button>
    </form>
    @include('billing.table')
    <div class="mt-5">{{ $bills->links() }}</div>
</section>
@endsection
