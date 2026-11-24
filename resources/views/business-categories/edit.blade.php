@extends('layouts.app')
@section('title', $category->exists ? $category->name : 'Add business category')

@section('actions')
    <div class="flex flex-wrap gap-2">
        @if ($category->exists)
            @can('tenants.view')
                <a class="btn secondary" href="{{ route('records.index', ['type' => 'tenants', 'business_category_id' => $category->id]) }}">View associated businesses</a>
            @endcan
        @endif
        <a class="btn secondary" href="{{ route('business-categories.index') }}">Back to categories</a>
    </div>
@endsection

@section('content')
    @php($errorApplies = !session()->hasOldInput('form_key') && !session()->hasOldInput('draft_fingerprint'))
    @can('rates.manage')
        <form class="panel max-w-3xl" method="post" action="{{ $category->exists ? route('business-categories.update', $category) : route('business-categories.store') }}">
            @csrf
            @if ($category->exists) @method('PUT') @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="category-code">Category code</label>
                    <input id="category-code" @if ($category->exists) value="{{ $category->code }}" readonly @else name="code" value="{{ old('code') }}" pattern="[A-Z0-9]+(-[A-Z0-9]+)*" maxlength="40" required @endif>
                </div>
                <div>
                    <label for="category-name">Name</label>
                    <input id="category-name" name="name" value="{{ old('name', $category->name) }}" maxlength="255" required @include('components.field-error-attributes', ['field'=>'name','id'=>'category-name'])>@include('components.field-error', ['field'=>'name','id'=>'category-name'])
                </div>
                <div>
                    <label for="category-order">Display order</label>
                    <input id="category-order" type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" max="4294967295" required @include('components.field-error-attributes', ['field'=>'sort_order','id'=>'category-order'])>@include('components.field-error', ['field'=>'sort_order','id'=>'category-order'])
                </div>
                <div>
                    <label for="category-active">Status</label>
                    <select id="category-active" name="is_active" @include('components.field-error-attributes', ['field'=>'is_active','id'=>'category-active'])>
                        <option value="1" @if ((string) old('is_active', (int) ($category->is_active ?? true)) === '1') selected @endif>Active</option>
                        <option value="0" @if ((string) old('is_active', (int) ($category->is_active ?? true)) === '0') selected @endif>Inactive</option>
                    </select>@include('components.field-error', ['field'=>'is_active','id'=>'category-active'])
                </div>
                <div class="sm:col-span-2">
                    <label for="category-description">Description</label>
                    <textarea id="category-description" name="description" maxlength="2000" @include('components.field-error-attributes', ['field'=>'description','id'=>'category-description'])>{{ old('description', $category->description) }}</textarea>@include('components.field-error', ['field'=>'description','id'=>'category-description'])
                </div>
            </div>
            <button class="btn mt-4">Save category</button>
        </form>
    @else
        <div class="panel">
            <p>{{ $category->code }} · {{ $category->is_active ? 'Active' : 'Inactive' }}</p>
            @if ($category->description)<p class="mt-2">{{ $category->description }}</p>@endif
        </div>
    @endcan

    @if ($category->exists)
        <section class="mt-6" aria-labelledby="rate-history">
            <h2 id="rate-history" class="mb-3">Rental rate history</h2>
            @can('rates.manage')
                <details class="panel mb-4" @if (old('form_key') === 'new-rate') open @endif>
                    <summary class="cursor-pointer font-semibold">Add draft rate</summary>
                    @include('business-categories.schedule-form', ['schedule' => null, 'policyForm' => false, 'action' => route('business-categories.rates.store', $category), 'formKey' => 'new-rate'])
                </details>
            @endcan

            @forelse ($rates as $rate)
                @include('business-categories.revision', [
                    'schedule' => $rate, 'policyForm' => false, 'formKey' => 'rate-'.$rate->id,
                    'updateUrl' => route('business-categories.rates.update', [$category, $rate]),
                    'approveUrl' => route('business-categories.rates.approve', [$category, $rate]),
                    'retireUrl' => route('business-categories.rates.retire', [$category, $rate]),
                ])
            @empty
                <p class="panel">No rental rate revisions found.</p>
            @endforelse
            <div class="mt-4">{{ $rates->links() }}</div>
        </section>
    @endif
@endsection
