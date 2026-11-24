@extends('layouts.app')
@section('title','Rental Billing')
@section('subtitle','Review draft bills before explicit posting. Posted amounts remain immutable.')
@section('actions')@can('billing.generate')<a class="btn" href="{{ route('billing.create') }}">Generate draft bills</a>@endcan @endsection
@section('content')<section class="panel"><form class="toolbar mb-5"><div><label for="period">Billing month</label><input id="period" type="month" name="period" value="{{ request('period') }}"></div><div><label for="status">Posting status</label><select id="status" name="status"><option value="">All</option><option @if(request('status')==='draft')selected @endif>draft</option><option @if(request('status')==='posted')selected @endif>posted</option></select></div><button class="btn secondary">Filter</button></form>@include('billing.table')<div class="mt-5">{{ $bills->links() }}</div></section>@endsection
