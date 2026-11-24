@extends('layouts.app')
@section('title','Rental Override History')
@section('subtitle',$tenant->name.' · '.$assignment->stall->code.' · '.(optional($assignment->business)->name ?? 'Business unavailable'))
@section('actions')
<div class="flex flex-wrap gap-2">
    <a class="btn secondary" href="{{ route('records.show',['tenants',$tenant->id,'tab'=>auth()->user()->can('billing.view') ? 'rental' : 'overview','period'=>$period]) }}">Tenant record</a>
    @if($earliest)<a class="btn" href="{{ route('rental-overrides.create',compact('tenant','assignment','period')) }}">Request manual override</a>@endif
</div>
@endsection
@section('content')
@if(session('rental_mode_notice'))<p class="notice" role="status">{{ session('rental_mode_notice') }}</p>@endif
@php
    $context = compact('tenant','assignment','period');
@endphp
<section class="panel">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <h2>Applicable rental · {{ $period }}</h2>
            <p class="mt-2 text-sm text-slate-600">Reference-month preview for this assignment. Issued records keep their saved assessments.</p>
        </div>
        <form method="get" class="toolbar">
            <div><label for="override-period">Reference month</label><input id="override-period" name="period" type="month" value="{{ $period }}" required></div>
            <button class="btn secondary" type="submit">Apply month</button>
        </form>
    </div>
    @include('rental-overrides.quote')
</section>
<section class="panel mt-5">
    <h2>Requests and decisions</h2>
    <p class="text-sm text-slate-600 mt-2">Request status is shown on the first day of {{ $period }}. Only applicable approved terms change rent.</p>
    @forelse($rows as $override)
        @php
            $status = $override->displayStatus($period.'-01');
            $approval = $review[$override->id] ?? null;
            $policy = $override->authority_snapshot ?? ($approval['policy'] ?? null);
            $rowContext = $context + compact('override');
        @endphp
        <article class="border-t border-slate-200 mt-5 pt-5" id="request-{{ $override->id }}">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h3>Request #{{ $override->id }} · Revision {{ $override->revision }}</h3>
                <span class="badge {{ $status === 'approved' ? 'status-occupied' : ($status === 'pending' ? 'status-reserved' : '') }}">{{ ucfirst($status) }}</span>
            </div>
            <dl class="mt-2">
                <div class="detailrow"><dt>Requested by</dt><dd>{{ $override->requester->name }}</dd></div>
                @if($override->source_override_id)<div class="detailrow"><dt>Revised from</dt><dd>Request #{{ $override->source_override_id }}</dd></div>@endif
                <div class="detailrow"><dt>Requested effective period</dt><dd>{{ $override->effective_from }} – {{ $override->effective_until ?? 'Open-ended' }}</dd></div>
                @if($status === 'approved' && $override->effective_from > $period.'-01')
                    <div class="detailrow"><dt>Applicability</dt><dd>Scheduled for a later month</dd></div>
                @endif
                <div class="detailrow"><dt>Frozen category / location / floor area</dt><dd>{{ $override->initialAssessment->business_category_name }} · {{ ucfirst($override->initialAssessment->location_type) }} · {{ $override->initialAssessment->floor_area }} sq. m.</dd></div>
                <div class="detailrow"><dt>Automatic rate and approved range</dt><dd>PHP {{ $override->automatic_rate_per_sqm }} per sq. m.<br>PHP {{ $override->initialAssessment->minimum_rate }}–{{ $override->initialAssessment->maximum_rate }} per sq. m.</dd></div>
                <div class="detailrow"><dt>Automatic daily rent</dt><dd>PHP {{ $override->automatic_daily_rental }}</dd></div>
                <div class="detailrow"><dt>Method and entered amount</dt><dd>{{ $override->override_type === 'rate_per_sqm' ? 'PHP '.$override->override_rate_per_sqm.' per sq. m.' : 'PHP '.$override->override_daily_amount.' direct daily' }}</dd></div>
                <div class="detailrow"><dt>Proposed daily rent</dt><dd>PHP {{ $override->proposed_daily_rental }}</dd></div>
                <div class="detailrow"><dt>Proposed daily difference</dt><dd>PHP {{ $override->rental_difference }}</dd></div>
                <div class="detailrow"><dt>Reason</dt><dd class="min-w-0 whitespace-pre-wrap">{{ $override->reason }}</dd></div>
                @if($override->reference_document !== null)<div class="detailrow"><dt>Supporting reference</dt><dd class="min-w-0">{{ $override->reference_document }}</dd></div>@endif
                @if($override->remarks !== null)<div class="detailrow"><dt>Remarks</dt><dd class="min-w-0 whitespace-pre-wrap">{{ $override->remarks }}</dd></div>@endif
                @if($override->approved_at)
                    <div class="detailrow"><dt>Approved by</dt><dd>{{ $override->approver->name }} · {{ $override->approved_at->format('Y-m-d H:i') }}</dd></div>
                @endif
                @if($override->rejection_reason !== null)<div class="detailrow"><dt>Rejection reason · {{ optional($override->rejected_at)->format('Y-m-d H:i') }}</dt><dd class="min-w-0">{{ $override->rejection_reason }}</dd></div>@endif
                @if($override->revocation_effective_from)
                    <div class="detailrow"><dt>Revocation effective</dt><dd>{{ $override->revocation_effective_from }}</dd></div>
                    <div class="detailrow"><dt>Revocation reason</dt><dd class="min-w-0">{{ $override->revocation_reason }}</dd></div>
                @endif
            </dl>
            @if($policy)
                <details class="mt-4" @if($approval) open @endif>
                    <summary class="cursor-pointer font-medium text-sm">{{ $approval ? 'Policy for this approval' : 'Policy recorded at approval' }}</summary>
                    <dl class="mt-2">
                        <div class="detailrow"><dt>Policy revision / reference</dt><dd>#{{ $policy['id'] }} · {{ $policy['reference_document'] }}</dd></div>
                        <div class="detailrow"><dt>Policy effective period</dt><dd>{{ $policy['effective_from'] }} – {{ $policy['effective_until'] ?? 'Open-ended' }}</dd></div>
                        <div class="detailrow"><dt>Out-of-range overrides authorized</dt><dd>{{ $policy['allow_out_of_range_overrides'] ? 'Yes' : 'No' }}</dd></div>
                        <div class="detailrow"><dt>Zero-rent overrides authorized</dt><dd>{{ $policy['allow_zero_rent_overrides'] ? 'Yes' : 'No' }}</dd></div>
                        <div class="detailrow"><dt>Exception authority reference</dt><dd>{{ $policy['override_authority_reference'] ?? '—' }}</dd></div>
                    </dl>
                </details>
            @endif
            @if($approval && !$policy)<p class="notice mt-4">One approved policy must cover the entire requested period before approval.</p>@endif
            @if($approval && ($approval['out_of_range'] || $approval['zero_rent']))
                <div class="notice mt-4">
                    @if($approval['out_of_range'])<p>Proposed daily rent is outside the approved category range.</p>@endif
                    @if($approval['zero_rent'])<p>This proposal requests a zero-rent exemption. CUSA remains payable.</p>@endif
                    <p>Approval requires explicit policy authority and supporting references.</p>
                </div>
            @endif
            <div class="flex flex-wrap gap-3 items-start mt-4">
                @if(isset($fingerprints[$override->id]))
                    <a class="btn secondary" href="{{ route('rental-overrides.edit',$rowContext) }}">Edit draft</a>
                    @if($earliest)
                        <form method="post" action="{{ route('rental-overrides.submit',$rowContext) }}" data-confirm="Submit these saved terms for review?">
                            @csrf
                            <input type="hidden" name="draft_fingerprint" value="{{ $fingerprints[$override->id] }}">
                            <input type="hidden" name="draft_terms_hash" value="{{ $override->request_hash }}">
                            <button class="btn" type="submit">Submit saved draft</button>
                        </form>
                    @endif
                @elseif($earliest && $override->status !== 'draft')
                    <a class="btn secondary" href="{{ route('rental-overrides.create',$context + ['source_override_id'=>$override->id]) }}">Create revised request</a>
                @endif
                @if($approval && $policy)
                    <form method="post" action="{{ route('rental-overrides.approve',$rowContext) }}" data-confirm="Approve the displayed request and policy?">
                        @csrf
                        <input type="hidden" name="fingerprint" value="{{ $approval['fingerprint'] }}">
                        <button class="btn" type="submit">Approve request</button>
                    </form>
                @endif
            </div>
            @if($override->status === 'pending')
                @can('rentals.override.reject')
                    @include('rental-overrides.decision',['action'=>'reject','label'=>'Reject request'])
                @endcan
            @elseif($override->status === 'approved' && $override->displayStatus() === 'approved')
                @can('rentals.override.revoke')
                    @include('rental-overrides.decision',['action'=>'revoke','label'=>'Return to automatic'])
                @endcan
            @endif
        </article>
    @empty
        <p class="empty">No rental override requests recorded.</p>
    @endforelse
    <div class="mt-5">{{ $rows->links() }}</div>
</section>
@endsection
