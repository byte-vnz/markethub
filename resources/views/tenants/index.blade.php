@extends('layouts.app')
@section('title','Tenant Management')
@section('subtitle','Search stallholders, businesses and assignment records.')
@section('actions')
    @can('tenants.manage')<a class="btn" href="{{ route('records.create','tenants') }}">Register tenant</a>@endcan
@endsection
@section('content')
<div class="flex flex-wrap gap-3 mb-4">
    <a class="btn secondary" href="{{ route('tenant-businesses.index') }}">Search businesses</a>
    @can('reports.personal')<a class="btn secondary" href="{{ route('reports.show','tenant-master') }}">Stallholder masterlist</a>@endcan
</div>
<section class="panel" aria-label="Tenant list">
    <form class="grid sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5" method="get">
        <div class="sm:col-span-2"><label for="q">Tenant, business, stall, account or contact</label><input type="search" id="q" name="q" value="{{ request('q') }}" maxlength="100"></div>
        <div><label for="status">Tenant status</label><select id="status" name="status"><option value="">All statuses</option>@foreach($tenantStatuses as $status)<option value="{{ $status }}" @if(request('status')===$status)selected @endif>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div><label for="stall">Stall code</label><input id="stall" name="stall" value="{{ request('stall') }}" maxlength="40"></div>
        @foreach(['business_category_id'=>['Business category',$businessCategories],'category_id'=>['Physical stall category',$categories],'market_id'=>['Market',$markets]] as $field=>[$label,$options])
            <div><label for="{{ $field }}">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}"><option value="">All</option>@foreach($options as $option)<option value="{{ $option->id }}" @if(request($field)==$option->id)selected @endif>{{ $option->name }}</option>@endforeach</select></div>
        @endforeach
        <div><label for="section_id">Section</label><select id="section_id" name="section_id"><option value="">All sections</option>
            @foreach($sections as $section)
                <option value="{{ $section->id }}" @if(request('section_id') == $section->id) selected @endif>{{ $markets->firstWhere('id',$section->market_id)->name ?? 'Unknown market' }} · {{ $section->name }}@if($section->status === 'inactive') (Inactive)@endif</option>
            @endforeach
        </select></div>
        <div><label for="location_type">Stall location</label><select id="location_type" name="location_type"><option value="">All locations</option>@foreach(['inner','outer','unclassified'] as $location)<option value="{{ $location }}" @if(request('location_type')===$location)selected @endif>{{ ucfirst($location) }}</option>@endforeach</select></div>
        @can('billing.view')
            <div><label for="payment_status">Payment status</label><select id="payment_status" name="payment_status"><option value="">All payment statuses</option>@foreach(['overdue'=>'Overdue','outstanding'=>'Outstanding (including overdue)','settled'=>'Settled','unbilled'=>'Unbilled'] as $value=>$label)<option value="{{ $value }}" @if(request('payment_status')===$value)selected @endif>{{ $label }}</option>@endforeach</select></div>
        @endcan
        @can('contracts.view')
            <div><label for="lease_status">Lease status</label><select id="lease_status" name="lease_status"><option value="">All lease statuses</option>@foreach($leaseStatuses as $status=>$label)<option value="{{ $status }}" @if(request('lease_status')===$status)selected @endif>{{ $label }}</option>@endforeach</select></div>
        @endcan
        <div class="flex flex-wrap items-end gap-2"><button type="submit" class="btn secondary">Filter</button><a class="btn secondary" href="{{ route('records.index','tenants') }}">Clear</a></div>
    </form>
    <div class="tablewrap" role="region" aria-label="Tenant records" tabindex="0"><table>
        <thead><tr><th scope="col">Stallholder</th><th scope="col">Businesses</th><th scope="col">Active stalls</th>
            @can('billing.view')<th scope="col">Rental and balance</th>@endcan
            @can('payments.view')<th scope="col">Latest payment</th>@endcan
            @can('contracts.view')<th scope="col">Latest lease</th>@endcan
            <th scope="col">Status</th><th scope="col">Actions</th>
        </tr></thead>
        <tbody>
            @forelse($records as $record)
                <tr>
                    <td>
                        <a class="font-semibold text-blue-800 underline" href="{{ route('records.show',['tenants',$record->id]) }}">{{ $record->name }}</a>
                        <div class="text-xs text-slate-600 mt-1">{{ $record->reference }}</div>
                        @if($record->present_account_number || $record->phone)<div class="text-xs text-slate-600 mt-1">{{ implode(' · ', array_filter([$record->present_account_number,$record->phone])) }}</div>@endif
                    </td>
                    <td>
                        @forelse($record->businesses->take(3) as $business)
                            <div>{{ $business->name ?: 'Individual stallholder' }} <span class="text-xs text-slate-600">· {{ optional($business->businessCategory)->name ?: 'Unclassified' }}</span></div>
                        @empty — @endforelse
                        @if($record->businesses->count() > 3)<a class="text-xs text-blue-800 underline" href="{{ route('records.show',['tenants',$record->id,'tab'=>'businesses']) }}">See all {{ $record->businesses->count() }} businesses</a>@endif
                    </td>
                    <td>
                        @forelse($record->assignments->take(3) as $assignment)
                            <div>{{ $assignment->stall->code }} · {{ ucfirst($assignment->stall->location_type ?: 'unclassified') }}</div>
                            <div class="text-xs text-slate-600">{{ $assignment->stall->market->name }}</div>
                        @empty Unassigned @endforelse
                        @if($record->assignments->count() > 3)<span class="text-xs text-slate-600">{{ $record->assignments->count() }} total</span>@endif
                    </td>
                    @can('billing.view')
                        <td>
                            @foreach($record->assignments->take(3) as $assignment)
                                @php($agreement = $assignment->agreement)
                                @if($agreement)<div>{{ $assignment->stall->code }} · PHP {{ $agreement->monthly_amount }} <span class="text-xs text-slate-600">{{ $agreement->assessment ? 'Initial assessment · '.$agreement->assessment->period : 'Legacy agreement' }}</span></div>@endif
                            @endforeach
                            <div class="text-xs mt-1 {{ bccomp((string)$record->overdue_balance,'0',2)>0 ? 'text-red-800' : 'text-slate-600' }}">
                                @if(!$record->posted_bill_count) Unbilled
                                @elseif(bccomp((string)$record->outstanding_balance, '0', 2) > 0)
                                    {{ bccomp((string)$record->overdue_balance,'0',2)>0 ? 'Overdue · ' : 'Outstanding · ' }} PHP {{ bcadd((string)$record->outstanding_balance,'0',2) }} balance
                                @else Settled @endif
                            </div>
                        </td>
                    @endcan
                    @can('payments.view')<td>{{ $record->latest_payment_date ?: '—' }}</td>@endcan
                    @can('contracts.view')
                        <td>@if($contract = $record->contracts->sortByDesc('id')->first())<a class="text-blue-800 underline" href="{{ route('contracts.show',$contract) }}">{{ $contract->control_number }}</a><div class="text-xs text-slate-600">{{ ucfirst($contract->status) }}</div>@else No contract @endif</td>
                    @endcan
                    <td><span class="badge">{{ ucfirst($record->status) }}</span></td>
                    <td class="whitespace-nowrap">
                        <a class="underline text-blue-800 mr-2" href="{{ route('records.show',['tenants',$record->id]) }}">View<span class="sr-only"> {{ $record->name }} ({{ $record->reference }})</span></a>
                        @can('update',$record)<a class="underline text-blue-800" href="{{ route('records.edit',['tenants',$record->id]) }}">{{ $record->status === 'draft' ? 'Resume' : 'Edit' }}<span class="sr-only"> {{ $record->name }} ({{ $record->reference }})</span></a>@endcan
                    </td>
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ 5 + (int)auth()->user()->can('billing.view') + (int)auth()->user()->can('payments.view') + (int)auth()->user()->can('contracts.view') }}">No tenants match these filters.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="mt-5">{{ $records->links() }}</div>
</section>
@endsection
