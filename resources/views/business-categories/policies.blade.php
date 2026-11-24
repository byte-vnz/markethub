@extends('layouts.app')
@section('title', 'Rental Policies')
@section('subtitle', 'Market policy revisions for CUSA, billing days, concurrent assignments, and advances and deposits')

@section('actions')
    <a class="btn secondary" href="{{ route('business-categories.index') }}">Business categories</a>
@endsection

@section('content')
    <form class="panel mb-4 flex flex-wrap items-end gap-3" method="get" action="{{ route('rental-policies.index') }}">
        <div class="min-w-48">
            <label for="policy-market-filter">Filter by market</label>
            <select id="policy-market-filter" name="market_id">
                <option value="">All markets</option>
                @foreach ($markets as $market)
                    <option value="{{ $market->id }}" @if ((int) $marketId === $market->id) selected @endif>{{ $market->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn secondary">Filter</button>
    </form>

    @can('rates.manage')
        <details class="panel mb-4" @if (old('form_key') === 'new-policy') open @endif>
            <summary class="cursor-pointer font-semibold">Add draft policy</summary>
            @include('business-categories.schedule-form', ['schedule' => null, 'policyForm' => true, 'action' => route('rental-policies.store'), 'formKey' => 'new-policy'])
        </details>
    @endcan

    <section aria-labelledby="policy-history">
        <h2 id="policy-history" class="mb-3">Policy history</h2>
        @forelse ($policies as $policy)
            @include('business-categories.revision', [
                'schedule' => $policy, 'policyForm' => true, 'formKey' => 'policy-'.$policy->id,
                'updateUrl' => route('rental-policies.update', $policy),
                'approveUrl' => route('rental-policies.approve', $policy),
                'retireUrl' => route('rental-policies.retire', $policy),
            ])
        @empty
            <p class="panel">No rental policy revisions found.</p>
        @endforelse
        <div class="mt-4">{{ $policies->links() }}</div>
    </section>
@endsection
