@extends('layouts.app')
@section('title','Accounts Receivable')
@section('subtitle','Current balances from posted bills, adjustments and unreversed allocations.')
@section('content')<section class="panel"><div class="flex justify-between flex-wrap gap-4 mb-5"><strong class="text-xl">Outstanding: PHP {{ number_format((float)$total,2) }}</strong><a class="btn secondary" href="{{ route('receivables',['overdue'=>request('overdue')?0:1,'tenant_id'=>request('tenant_id')]) }}">{{ request('overdue')?'Show all balances':'Show overdue only' }}</a></div>@include('billing.table')<div class="mt-5">{{ $bills->links() }}</div></section>@endsection
