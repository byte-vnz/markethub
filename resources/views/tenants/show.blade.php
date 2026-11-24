@extends('layouts.app')
@section('title',$record->name)
@section('subtitle',$record->reference.' · '.ucfirst($record->status))
@section('actions')
    @can('update',$record)
        <a class="btn" href="{{ route('records.edit',['tenants',$record->id]) }}">{{ $record->status === 'draft' ? 'Resume registration' : 'Edit tenant' }}</a>
    @endcan
@endsection
@section('content')
@if(session('duplicate_warning'))<p class="notice" role="status">{{ session('duplicate_warning') }}</p>@endif
@if(session('rental_mode_notice'))<p class="notice" role="status">{{ session('rental_mode_notice') }}</p>@endif
@if(session('registered_rental_link') && isset($tabs['rental']))
    <p class="notice" role="status">The assignment starts with automatic rent.
        <a class="underline font-semibold" href="{{ route('records.show',['tenants',$record->id,'tab'=>'rental']) }}">Open Rental Information</a> to review the basis or request an override.
    </p>
@endif
<nav class="flex flex-wrap gap-x-4 gap-y-1 border-b border-slate-200 mb-5" aria-label="Tenant profile sections">
    @foreach($tabs as $key=>$label)
        <a class="inline-flex min-h-11 items-center border-b-2 py-2 px-1 text-sm {{ $tab===$key ? 'border-blue-700 font-semibold text-blue-900' : 'border-transparent text-slate-700 hover:text-blue-800 hover:underline' }}" href="{{ route('records.show',['tenants',$record->id,'tab'=>$key]) }}" @if($tab===$key)aria-current="page"@endif>{{ $label }}</a>
    @endforeach
</nav>

@switch($tab)
@case('overview')
    <section class="panel" aria-labelledby="overview-heading">
        <h2 id="overview-heading">Overview</h2>
        @if($record->status === 'draft')<p class="notice mt-3">Registration is in draft. Pending stall selections do not reserve occupancy.</p>@endif
        <dl class="mt-3">
            <div class="detailrow"><dt>Present account</dt><dd>{{ $record->present_account_number ?: '—' }}</dd></div>
            <div class="detailrow"><dt>Contact number</dt><dd>{{ $record->phone ?: '—' }}</dd></div>
            <div class="detailrow"><dt>Active businesses</dt><dd>{{ $businessCount }}</dd></div>
            @can('assignments.view')<div class="detailrow"><dt>Active assignments</dt><dd>{{ $assignmentCount }}</dd></div>@endcan
            @can('billing.view')<div class="detailrow"><dt>Outstanding posted balance</dt><dd>PHP {{ $balance }}</dd></div>@endcan
            @can('payments.view')<div class="detailrow"><dt>Latest effective payment</dt><dd>@if($lastPayment)<a class="text-blue-800 underline" href="{{ route('payments.show',$lastPayment) }}">{{ $lastPayment->payment_date }} · PHP {{ $lastPayment->amount }}</a>@else None recorded @endif</dd></div>@endcan
        </dl>
        <div class="flex flex-wrap gap-2 mt-4">
            @can('reports.personal')<a class="btn secondary" href="{{ route('reports.show',['type'=>'tenant-statement','tenant_id'=>$record->id]) }}">Statement of account</a>@endcan
            @can('payments.record')<a class="btn secondary" href="{{ route('payments.create',['tenant_id'=>$record->id]) }}">Record payment</a>@endcan
        </div>
    </section>
    @break

@case('personal')
    <div class="grid xl:grid-cols-2 gap-5">
        <section class="panel"><h2>Personal information</h2><dl>
            @foreach(['name'=>'Full name','civil_status'=>'Civil status','gender'=>'Gender','citizenship'=>'Citizenship','address'=>'Permanent residential address','phone'=>'Contact number','email'=>'Email','remarks'=>'Remarks'] as $field=>$label)
                <div class="detailrow"><dt>{{ $label }}</dt><dd>{{ $record->$field ?: '—' }}</dd></div>
            @endforeach
        </dl></section>
        <section class="panel"><h2>Account and identification</h2><dl>
            @foreach(['previous_account_number'=>'Previous account number','present_account_number'=>'Present account number','government_id_type'=>'Government-issued ID type'] as $field=>$label)
                <div class="detailrow"><dt>{{ $label }}</dt><dd>{{ $record->$field ?: '—' }}</dd></div>
            @endforeach
            <div class="detailrow"><dt>ID number</dt><dd>@can('tenants.view_sensitive'){{ $record->government_id_number ?: '—' }}@else{{ $record->masked_government_id_number ?: '—' }}@endcan</dd></div>
            @foreach(['government_id_date_issued'=>'Date issued','government_id_place_issued'=>'Place issued','government_id_issuance_exception_reason'=>'Issuance exception reason'] as $field=>$label)
                <div class="detailrow"><dt>{{ $label }}</dt><dd>{{ $record->$field ?: '—' }}</dd></div>
            @endforeach
        </dl></section>
    </div>
    @break

@case('businesses')
    <section class="panel">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2>Businesses</h2>
            @can('update',$record)<a class="btn" href="{{ route('tenant-businesses.create',$record) }}">Register business</a>@endcan
        </div>
        @forelse($profileBusinesses as $business)
            <article class="mt-5 border-t border-slate-200 pt-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3>{{ $business->name ?: 'Individual stallholder' }}</h3><p class="mt-1 text-sm text-slate-600">{{ optional($business->businessCategory)->name ?: ($business->category ? 'Legacy category · '.$business->category : 'Unclassified') }} · {{ ucfirst($business->status) }}</p></div>
                    @can('update',$record)
                        <div class="flex flex-wrap gap-2">
                            <a class="btn secondary" href="{{ route('tenant-businesses.edit',[$record,$business]) }}">Edit business</a>
                            @if($business->status !== 'archived')
                                <form method="post" action="{{ route('tenant-businesses.archive',[$record,$business]) }}" data-confirm="Archive this business and keep its assignment history?">
                                    @csrf <button class="btn danger" type="submit">Archive</button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </div>
                <dl class="mt-3">
                    @foreach(['line_of_business'=>'Line of business','permitted_goods'=>'Permitted goods or services','description'=>'Legacy description','permit_reference'=>'Permit reference','registration_reference'=>'Registration reference','remarks'=>'Remarks'] as $field=>$label)
                        @if($business->$field)<div class="detailrow"><dt>{{ $label }}</dt><dd>{{ $business->$field }}</dd></div>@endif
                    @endforeach
                    @if($business->permit_from || $business->permit_until)<div class="detailrow"><dt>Permit dates</dt><dd>{{ $business->permit_from ?: '—' }} – {{ $business->permit_until ?: '—' }}</dd></div>@endif
                </dl>
            </article>
        @empty <p class="text-sm text-slate-600 mt-4">No businesses registered for this tenant.</p> @endforelse
        <div class="mt-4">{{ $profileBusinesses->links() }}</div>
    </section>
    @break

@case('assignments')
    <section class="panel" id="assignments">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2>Stall assignments</h2>
            @can('assignments.manage')
                @if($record->status==='active')<a class="btn" href="{{ route('assignments.create',['tenant_id'=>$record->id]) }}">Assign existing stall</a>@endif
            @endcan
        </div>
        @forelse($profileAssignments as $assignment)
            <article class="mt-5 border-t border-slate-200 pt-4">
                <h3>{{ $assignment->stall->code }} <span class="badge">{{ ucfirst($assignment->status) }}</span></h3>
                <dl class="mt-2">
                    <div class="detailrow"><dt>Market and section</dt><dd>{{ $assignment->stall->market->name }} · {{ $assignment->stall->section->name }}</dd></div>
                    <div class="detailrow"><dt>Current location and area</dt><dd>{{ ucfirst($assignment->stall->location_type ?: 'unclassified') }} · {{ $assignment->stall->area }} sq. m.</dd></div>
                    <div class="detailrow"><dt>Occupancy dates</dt><dd>{{ $assignment->start_date }} – {{ $assignment->end_date ?: 'Ongoing' }}</dd></div>
                    <div class="detailrow"><dt>Business record</dt><dd>{{ $assignment->business ? ($assignment->business->name ?: 'Individual stallholder') : 'No business linked' }}</dd></div>
                    @if($assignment->source_stall_count!==null || $assignment->registered_stall_count!==null)<div class="detailrow"><dt>Source / registered stalls per category</dt><dd>{{ $assignment->source_stall_count ?? '—' }} / {{ $assignment->registered_stall_count ?? '—' }}</dd></div>@endif
                </dl>
                <div class="flex flex-wrap gap-3 mt-3">
                    @can('stalls.view')<a class="text-sm text-blue-800 underline" href="{{ route('records.show',['stalls',$assignment->stall_id]) }}">View stall</a>@endcan
                    @if($assignment->status==='active')
                        @can('assignments.manage')<a class="text-sm text-blue-800 underline" href="{{ route('assignments.transfer-form',$assignment) }}">Transfer or renew</a>@endcan
                        @can('contracts.create')<a class="text-sm text-blue-800 underline" href="{{ route('contracts.create',['assignment_id'=>$assignment->id]) }}">Prepare lease contract</a>@endcan
                    @endif
                </div>
            </article>
        @empty <p class="text-sm text-slate-600 mt-4">No stall assignments recorded.</p> @endforelse
        <div class="flex flex-wrap gap-3 mt-4">
            <a class="btn secondary" href="{{ route('assignments.index',['tenant_id'=>$record->id]) }}">Assignment register</a>
        </div>
        <div class="mt-4">{{ $profileAssignments->links() }}</div>
    </section>
    @break

@case('rental')
    <section class="panel">
        <div class="flex flex-wrap justify-between items-start gap-4">
            <h2>Rental information</h2>
            @can('rentals.override.view')
                <form class="toolbar" method="get">
                    <input type="hidden" name="tab" value="rental">
                    <div><label for="rental-period">Reference month</label><input id="rental-period" name="period" type="month" value="{{ $period }}" required></div>
                    <button class="btn secondary" type="submit">Apply month</button>
                </form>
            @endcan
        </div>
        <div class="detailrow mt-2"><span class="font-semibold">Outstanding posted balance</span><span>PHP {{ $balance }}</span></div>
        @forelse($profileAssignments as $assignment)
            @php($agreement = $assignment->agreement)
            @php($assessment = optional($agreement)->assessment)
            <article class="mt-5 border-t border-slate-200 pt-4">
                <h3>{{ $assessment ? $assessment->stall_code : $assignment->stall->code }} · {{ optional($agreement)->reference ?: 'No agreement' }}</h3>
                <p class="text-sm text-slate-600 mt-2">{{ optional($assignment->business)->name ?? 'Business unavailable' }} · {{ optional($assignment->stall->section)->name ?? 'Unknown section' }}@if(optional($assignment->stall->section)->status === 'inactive') (Inactive)@endif</p>
                @php($frozenSection = $assessment ? ($assessment->market_section_name ?? ($assessment->basis['stall_section_name'] ?? null)) : null)
                @if($frozenSection !== null && $frozenSection !== optional($assignment->stall->section)->name)
                    <p class="text-sm text-slate-600 mt-1">Assessed section {{ $frozenSection }}</p>
                @endif
                @can('rentals.override.view')
                    @php([$quote, $quoteError] = $rentalQuotes[$assignment->id])
                    <h4 class="mt-4 font-medium">Applicable rental preview · {{ $period }}</h4>
                    @include('rental-overrides.quote')
                    <div class="flex flex-wrap items-end gap-3 mt-4">
                        @if(!empty($requestMonths[$assignment->id]))
                            <form class="toolbar" method="get" action="{{ route('rental-overrides.create',['tenant'=>$record,'assignment'=>$assignment]) }}">
                                <input type="hidden" name="period" value="{{ $period }}">
                                <div><label for="rental-mode-{{ $assignment->id }}">Choose rental mode for {{ $assignment->stall->code }}</label>
                                    <select id="rental-mode-{{ $assignment->id }}" name="computation_mode">
                                        <option value="automatic">Automatic (default)</option>
                                        <option value="manual_override">Manual override request</option>
                                    </select>
                                </div>
                                <button class="btn" type="submit">Continue</button>
                            </form>
                        @endif
                        @can('rentals.override.history')
                            <a class="btn secondary" href="{{ route('rental-overrides.index',['tenant'=>$record,'assignment'=>$assignment,'period'=>$period]) }}">Override history</a>
                        @endcan
                    </div>
                    @if($assignment->pending_override_count)
                        <p class="text-sm text-slate-600 mt-2">{{ $assignment->pending_override_count }} {{ Illuminate\Support\Str::plural('pending request',$assignment->pending_override_count) }} awaiting review.</p>
                    @endif
                @endcan
                @if($assessment)
                    <details class="mt-4" @if(empty($quote)) open @endif>
                    <summary class="cursor-pointer font-medium text-sm">Initial assessment · {{ $assessment->period }}</summary>
                    <p class="text-sm text-slate-600 mt-2">{{ $assessment->business_category_name }} · {{ ucfirst($assessment->location_type) }}</p>
                    <dl class="mt-2">
                        <div class="detailrow"><dt>Assessed floor area</dt><dd>{{ $assessment->floor_area }} sq. m.</dd></div>
                        <div class="detailrow"><dt>Minimum / maximum per sq. m.</dt><dd>PHP {{ $assessment->minimum_rate }} / {{ $assessment->maximum_rate }}</dd></div>
                        <div class="detailrow"><dt>Applied rental rate per sq. m.</dt><dd>PHP {{ $assessment->rental_rate_sqm }}</dd></div>
                        <div class="detailrow"><dt>Daily rent / CUSA</dt><dd>PHP {{ $assessment->daily_rental }} / {{ $assessment->daily_cusa }}</dd></div>
                        <div class="detailrow"><dt>Billing days</dt><dd>{{ $assessment->billing_days }}</dd></div>
                        <div class="detailrow"><dt>Monthly rent / CUSA</dt><dd>PHP {{ $assessment->monthly_rental }} / {{ $assessment->monthly_cusa }}</dd></div>
                        <div class="detailrow"><dt>Monthly total for reference month</dt><dd>PHP {{ $assessment->total_monthly_rental_cusa }}</dd></div>
                        <div class="detailrow"><dt>Two months’ advance / security deposit</dt><dd>PHP {{ $assessment->advance_rental }} / {{ $assessment->security_deposit }}</dd></div>
                    </dl>
                    </details>
                @elseif($agreement)
                    <p class="text-sm text-slate-600 mt-2">Legacy monthly snapshot · PHP {{ $agreement->monthly_amount }}</p>
                @else
                    <p class="text-sm text-slate-600 mt-2">No rental agreement recorded.</p>
                @endif
            </article>
        @empty <p class="text-sm text-slate-600 mt-4">No rental agreements recorded.</p> @endforelse
        <div class="flex flex-wrap gap-3 mt-4"><a class="btn secondary" href="{{ route('billing.index',['tenant_id'=>$record->id]) }}">Billing history</a><a class="btn secondary" href="{{ route('receivables',['tenant_id'=>$record->id]) }}">Receivables</a></div>
        <div class="mt-4">{{ $profileAssignments->links() }}</div>
    </section>
    @break

@case('helpers')
    <section class="panel" id="helpers"><h2>Authorized helpers</h2>
        @forelse($profileAssignments as $assignment)
            <article class="mt-5 border-t border-slate-200 pt-4"><h3>{{ $assignment->stall->code }} · {{ $assignment->start_date }}</h3>
                @forelse($record->helpers->where('stall_assignment_id',$assignment->id)->sortBy('slot') as $helper)
                    <p class="text-sm mt-2">{{ $helper->full_name }} · {{ ucfirst($helper->status) }}{{ $helper->relationship ? ' · '.$helper->relationship : '' }}{{ $helper->phone ? ' · '.$helper->phone : '' }}</p>
                @empty <p class="text-sm text-slate-600 mt-2">No helpers recorded for this assignment.</p> @endforelse
                @can('update',$record)<a class="inline-block text-sm text-blue-800 underline mt-3" href="{{ route('records.edit',['tenants',$record->id,'assignment_id'=>$assignment->id]) }}#helpers">Manage helpers</a>@endcan
            </article>
        @empty <p class="text-sm text-slate-600 mt-4">Helper registrations appear after a stall is assigned.</p> @endforelse
        <div class="mt-4">{{ $profileAssignments->links() }}</div>
    </section>
    @break

@case('payments')
    <section class="panel">
        <div class="flex flex-wrap items-start justify-between gap-3"><h2 id="payment-history-heading">Payment history</h2>@can('payments.record')<a class="btn" href="{{ route('payments.create',['tenant_id'=>$record->id]) }}">Record payment</a>@endcan</div>
        <div class="tablewrap mt-3" role="region" aria-labelledby="payment-history-heading" tabindex="0"><table><thead><tr><th scope="col">Date</th><th scope="col">Receipt</th><th scope="col">Amount</th><th scope="col">Status</th></tr></thead><tbody>
            @forelse($profilePayments as $payment)
                <tr><td>{{ $payment->payment_date }}</td><td><a class="text-blue-800 underline" href="{{ route('payments.show',$payment) }}">{{ $payment->receipt_reference ?: $payment->reference }}</a></td><td>PHP {{ $payment->amount }}</td><td>{{ $payment->reversal ? 'Reversed' : ucfirst($payment->status) }}</td></tr>
            @empty <tr><td class="empty" colspan="4">No payments recorded.</td></tr> @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $profilePayments->links() }}</div>
    </section>
    @break

@case('contracts')
    <section class="panel">
        <div class="flex flex-wrap items-start justify-between gap-3"><h2 id="lease-contracts-heading">Lease contracts</h2>@can('contracts.create')@if($record->status==='active')<a class="btn" href="{{ route('contracts.create',['tenant_id'=>$record->id]) }}">Prepare lease contract</a>@endif @endcan</div>
        <div class="tablewrap mt-3" role="region" aria-labelledby="lease-contracts-heading" tabindex="0"><table><thead><tr><th scope="col">Contract</th><th scope="col">Stall</th><th scope="col">Period</th><th scope="col">Status</th></tr></thead><tbody>
            @forelse($contracts as $contract)
                <tr><td><a class="text-blue-800 underline" href="{{ route('contracts.show',$contract) }}">{{ $contract->control_number }}</a></td><td>{{ $contract->assignment->stall->code }}</td><td>{{ $contract->start_date }} – {{ $contract->end_date }}</td><td>{{ ucfirst($contract->status) }}</td></tr>
            @empty <tr><td class="empty" colspan="4">No lease contracts recorded.</td></tr> @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $contracts->links() }}</div>
    </section>
    @break

@case('activity')
    <section class="panel"><h2 id="activity-history-heading">Activity history</h2>
        <div class="tablewrap mt-3" role="region" aria-labelledby="activity-history-heading" tabindex="0"><table><thead><tr><th scope="col">Date and time</th><th scope="col">Action</th><th scope="col">Staff member</th></tr></thead><tbody>
            @forelse($activity as $event)
                <tr><td>{{ $event->created_at->format('Y-m-d H:i') }}</td><td>{{ ucfirst(str_replace(['.','_'], ' ', $event->action)) }}</td><td>{{ optional($event->actor)->name ?: 'System' }}</td></tr>
            @empty <tr><td class="empty" colspan="3">No audit events recorded.</td></tr> @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $activity->links() }}</div>
    </section>
    @break
@endswitch
@endsection
