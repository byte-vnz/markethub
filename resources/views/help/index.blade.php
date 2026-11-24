@extends('help.layout')
@section('help-content')
<section class="panel" aria-labelledby="find-help-heading">
    <h2 id="find-help-heading">Find a task or topic</h2>
    <p class="text-sm text-slate-600 mt-2">Browse the complete 14-chapter manual or search for a task, screen, status or error message.</p>
    <form method="get" action="{{ route('help.index') }}" class="toolbar mt-4" role="search" aria-label="Search help topics">
        <div class="flex-1"><label for="help-query">Search topics</label><input type="search" id="help-query" name="q" value="{{ $query }}" maxlength="160" placeholder="For example: tenant, payment or contract"></div>
        <div><label for="help-category">Category</label><select id="help-category" name="category"><option value="">All categories</option>@foreach(config('help.categories') as $key => $group)<option value="{{ $key }}" @if($category === $key) selected @endif>{{ $group['label'] }}</option>@endforeach</select></div>
        <button class="btn" type="submit">Search help</button>
        @if($query !== '' || $category)<a class="btn secondary" href="{{ route('help.index') }}">Clear</a>@endif
    </form>
</section>
<div class="mt-6" aria-live="polite">
    @if(!$topics)<div class="panel empty"><h2>No topics found</h2><p class="mt-2">Try a shorter phrase or choose All categories.</p></div>@endif
    @foreach(config('help.categories') as $key => $group)
        @php($matches = array_filter($topics, function ($topic) use ($key) { return $topic['category'] === $key; }))
        @if($matches)
        <section class="panel mb-4" aria-labelledby="category-{{ $key }}">
            <h2 id="category-{{ $key }}">{{ $group['label'] }}</h2>
            <ul class="mt-3 divide-y divide-slate-100">
            @foreach($matches as $slug => $topic)
                <li><a class="block py-3 text-blue-800 hover:underline" href="{{ route('help.manual') }}#{{ $slug }}">{{ $topic['title'] }} <span aria-hidden="true">→</span></a></li>
            @endforeach
            </ul>
        </section>
        @endif
    @endforeach
</div>
@endsection
