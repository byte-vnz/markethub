@extends('layouts.app')
@section('title',$title)
@section('subtitle',$reportNote ?? (in_array($type,['business-categories','rental-assessments','lease-contract-registry'],true)
    ? 'Category schedules, frozen assessments and contract history retain their source references. Only approved schedules authorize rental assessments.'
    : 'Current posted transaction values. Collection totals exclude reversed payments.'))
@section('content')
<section class="panel">
    <form class="toolbar mb-5" method="get">
        @if(!in_array($type, ['rental-overrides', 'stall-master', 'occupied-stalls', 'vacant-stalls', 'tenant-master'], true))
            @foreach(['from'=>'From date','to'=>'To date'] as $key=>$label)
                <div><label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" type="date" name="{{ $key }}" value="{{ $filters[$key] ?? '' }}"></div>
            @endforeach
        @endif
        @foreach(\App\Services\Reports::scopeFilters($type) as $key=>$label)
            <div><label for="{{ $key }}">{{ $label }} ID</label><input id="{{ $key }}" name="{{ $key }}" type="number" min="1" value="{{ $filters[$key] ?? '' }}" @if($type === 'tenant-statement' && $key === 'tenant_id') required @endif></div>
        @endforeach
        @if(in_array($type,['business-categories','rental-assessments','rental-overrides','lease-contract-registry'],true))
            <div><label for="business_category_id">Business category ID</label><input id="business_category_id" type="number" min="1" name="business_category_id" value="{{ $filters['business_category_id'] ?? '' }}"></div>
        @endif
        @if(in_array($type,['rental-assessments','lease-contract-registry'],true))
            <div>
                <label for="location_type">Assessed location</label>
                <select id="location_type" name="location_type">
                    <option value="">All locations</option>
                    @foreach(['inner'=>'Inner','outer'=>'Outer','unclassified'=>'Legacy / unclassified'] as $value=>$label)
                        <option value="{{ $value }}" @if(($filters['location_type'] ?? null) === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if(in_array($type,['rental-assessments','rental-overrides'],true))
            <div><label for="as_of">{{ $type === 'rental-overrides' ? 'Applicability date' : 'Recorded by date' }}</label>
                <input id="as_of" type="date" name="as_of" value="{{ $filters['as_of'] ?? '' }}">
            </div>
        @endif
        @if($type === 'rental-overrides' || ($type === 'rental-assessments' && $canSeeAssessmentMode))
            <div><label for="computation_mode">Rental computation</label><select id="computation_mode" name="computation_mode">
                <option value="">All modes</option>
                <option value="automatic" @if(($filters['computation_mode'] ?? null) === 'automatic') selected @endif>Automatic</option>
                <option value="manual_override" @if(($filters['computation_mode'] ?? null) === 'manual_override') selected @endif>Manual override</option>
            </select></div>
        @endif
        @if(in_array($type,['lease-contract-registry','rental-overrides'],true))
            <div>
                <label for="status">{{ $type === 'rental-overrides' ? 'Request status' : 'Lease status' }}</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach($type === 'rental-overrides' ? ['draft','pending','approved','rejected','revoked','expired'] : ['draft','ready','issued','signed','active','expired','terminated','void'] as $status)
                        <option value="{{ $status }}" @if(($filters['status'] ?? null) === $status) selected @endif>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button class="btn secondary" type="submit">Filter</button>
    </form>
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach(['csv'=>'CSV','xlsx'=>'Excel','pdf'=>'PDF','print'=>'Print view'] as $format=>$label)
            <a class="btn secondary" href="{{ route('reports.show', array_merge($filters, ['type'=>$type,'format'=>$format])) }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="tablewrap" role="region" aria-label="{{ $title }} results" tabindex="0">
        <table>
            <thead><tr>
                @if($rows->count())
                    @foreach(array_keys((array) $rows->first()) as $column)
                        <th scope="col">{{ ucwords(str_replace('_',' ',$column)) }}</th>
                    @endforeach
                @endif
            </tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>@foreach((array) $row as $value)<td>{{ $value ?? '—' }}</td>@endforeach</tr>
                @empty
                    <tr><td class="empty">No matching records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $rows->links() }}</div>
</section>
@endsection
