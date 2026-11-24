@extends('layouts.app')
@section('title', 'Business Categories')
@section('subtitle', 'Commercial categories and daily rent per square metre')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a class="btn secondary" href="{{ route('rental-policies.index') }}">Rental policies</a>
        @can('rates.manage')
            <a class="btn" href="{{ route('business-categories.create') }}">Add category</a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="panel tablewrap" role="region" aria-label="Business categories" tabindex="0">
        <table class="w-full">
            <thead>
                <tr><th scope="col">Category</th><th scope="col">Businesses</th><th scope="col">Assigned stalls</th><th scope="col">Inner minimum</th><th scope="col">Outer maximum</th><th scope="col">Rate revision</th><th scope="col">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    @php($rate = $category->rentalRates->firstWhere('status', 'approved') ?? $category->rentalRates->firstWhere('status', 'retired') ?? $category->rentalRates->first())
                    <tr>
                        <td>
                            <p class="font-semibold">{{ $category->name }}</p>
                            <p class="text-sm text-slate-600">{{ $category->code }} · {{ $category->is_active ? 'Active' : 'Inactive' }}</p>
                            @if ($category->description)<p class="mt-1 text-sm">{{ $category->description }}</p>@endif
                        </td>
                        <td>{{ $category->businesses_count }}</td>
                        <td>{{ $category->assigned_stalls_count }}</td>
                        <td>{{ $rate && $rate->minimum_rate !== null ? '₱'.$rate->minimum_rate : '—' }}</td>
                        <td>{{ $rate && $rate->maximum_rate !== null ? '₱'.$rate->maximum_rate : '—' }}</td>
                        <td>
                            @if ($rate)
                                {{ ucfirst($rate->status) }}
                                @if ($rate->effective_from)
                                    <p class="text-sm">{{ $rate->effective_from->toDateString() }} to {{ optional($rate->effective_until)->toDateString() ?? 'open' }}</p>
                                @endif
                            @else
                                No current or draft rate
                            @endif
                        </td>
                        <td><a class="text-blue-900 underline" href="{{ route('business-categories.edit', $category) }}" aria-label="Rate history for {{ $category->name }}">Rate history</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No business categories found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $categories->links() }}</div>
@endsection
