@extends('layouts.app')
@section('title','Stall Assignments')
@section('subtitle','Current occupancy and preserved assignment history.')
@section('actions')@can('assignments.manage')<a class="btn" href="{{ route('assignments.create') }}">Assign tenant</a>@endcan @endsection
@section('content')<section class="panel tablewrap" role="region" aria-label="Stall assignments" tabindex="0"><table><thead><tr><th scope="col">Stall / tenant</th><th scope="col">Agreement</th><th scope="col">Dates</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>@forelse($assignments as $a)<tr><td>{{ $a->stall->code }}<br>{{ $a->tenant->name }}<br>{{ optional($a->business)->name }}</td><td>@if($a->agreement)
{{ $a->agreement->reference }}<br>PHP {{ $a->agreement->monthly_amount }}
@if($a->agreement->assessment)
<br><small>{{ $a->agreement->assessment->period }} reference month · {{ $a->agreement->assessment->business_category_name }} · {{ ucfirst($a->agreement->assessment->location_type) }}</small>
@else
<br><small>Legacy monthly rent</small>
@endif
@endif</td><td>{{ $a->start_date }}<br>{{ $a->end_date ?: 'Ongoing' }}</td><td>{{ $a->status }}</td><td>@can('assignments.manage')@if($a->status==='active')<a class="text-blue-800 underline" href="{{ route('assignments.transfer-form',$a) }}">Transfer / renew</a><details class="mt-3"><summary class="cursor-pointer">End assignment</summary><form method="post" action="{{ route('assignments.end',$a) }}" data-confirm="End this assignment?">@csrf<label for="end-{{ $a->id }}">End date</label><input id="end-{{ $a->id }}" type="date" name="end_date" value="{{ today()->toDateString() }}" required><label for="reason-{{ $a->id }}">Reason</label><input id="reason-{{ $a->id }}" name="reason" minlength="5" required><button class="btn danger mt-3">End assignment</button></form></details>@endif @endcan</td></tr>@empty<tr><td colspan="5" class="empty">No assignments recorded.</td></tr>@endforelse</tbody></table><div class="mt-5">{{ $assignments->links() }}</div></section>@endsection
