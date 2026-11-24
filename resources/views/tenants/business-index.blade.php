@extends('layouts.app')
@section('title','Business search')
@section('subtitle','Find a business and open its tenant profile.')
@section('actions')
    <a class="btn secondary" href="{{ route('records.index','tenants') }}">Tenant Management</a>
@endsection
@section('content')
<section class="panel">
    <form class="toolbar mb-5" method="get" action="{{ route('tenant-businesses.index') }}">
        <div><label for="q">Business name, trade or tenant</label><input type="search" id="q" name="q" value="{{ request('q') }}" maxlength="100"></div>
        <div><label for="status">Business status</label><select name="status" id="status"><option value="">All statuses</option>@foreach(['active','inactive','archived'] as $status)<option value="{{ $status }}" @if(request('status')===$status)selected @endif>{{ ucfirst($status) }}</option>@endforeach</select></div>
        @if(request()->filled('tenant_id'))<input type="hidden" name="tenant_id" value="{{ request('tenant_id') }}">@endif
        @can('tenants.manage')
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="orphaned" value="1" @if(request()->boolean('orphaned'))checked @endif> Unlinked records only</label>
        @endcan
        <button class="btn secondary" type="submit">Search</button><a class="btn secondary" href="{{ route('tenant-businesses.index') }}">Clear</a>
    </form>

    <div class="tablewrap" role="region" aria-label="Business search results" tabindex="0"><table><thead><tr><th scope="col">Business</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Tenant profile</th></tr></thead><tbody>
    @forelse($businesses as $business)
        <tr>
            <td>{{ $business->name ?: 'Individual stallholder' }}</td>
            <td>{{ optional($business->businessCategory)->name ?: ($business->category ? 'Legacy · '.$business->category : 'Unclassified') }}</td>
            <td>{{ ucfirst($business->status) }}</td>
            <td>
                @if($business->tenant)
                    <a class="text-blue-800 font-semibold underline" href="{{ route('records.show',['tenants',$business->tenant_id,'tab'=>'businesses']) }}">{{ $business->tenant->name }}</a>
                @else
                    <details class="max-w-md">
                        <summary class="cursor-pointer text-blue-800 font-semibold underline">Reconcile unlinked business</summary>
                        <form class="space-y-3 mt-3" method="post" action="{{ route('tenant-businesses.reconcile',$business) }}" data-confirm="Link this record to the verified tenant?">
                            @csrf
                            <div><label for="tenant-reference-{{ $business->id }}">Verified tenant reference</label><input id="tenant-reference-{{ $business->id }}" name="tenant_reference" required maxlength="60"></div>
                            <div><label for="reason-{{ $business->id }}">Reconciliation reason</label><textarea id="reason-{{ $business->id }}" name="reason" required maxlength="2000" rows="2"></textarea></div>
                            <button class="btn secondary" type="submit">Link verified tenant</button>
                        </form>
                    </details>
                @endif
            </td>
        </tr>
    @empty <tr><td class="empty" colspan="4">No businesses match these filters.</td></tr> @endforelse
    </tbody></table></div>
    <div class="mt-5">{{ $businesses->links() }}</div>
</section>
@endsection
