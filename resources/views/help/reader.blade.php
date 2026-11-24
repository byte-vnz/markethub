@extends('help.layout')
@section('help-content')
<div class="help-reading @if($chapters) with-toc @endif">
    @if($chapters)
    <nav class="panel help-toc" aria-label="Manual table of contents">
        <h2>Manual contents</h2>
        <ol class="mt-3">@foreach($chapters as $slug => $chapter)<li><a href="{{ route('help.manual') }}#{{ $slug }}">{{ $chapter['title'] }}</a></li>@endforeach</ol>
    </nav>
    @endif
    <article class="panel help-prose" aria-label="{{ $heading }} content">
    @forelse($sections as $section)
        {!! $section['html'] !!}
    @empty
        <h2>Guide not available yet</h2><p>Please return to <a href="{{ route('help.index') }}">Help home</a> to browse available topics.</p>
    @endforelse
    @if($page === 'workflows')
        @foreach(config('help.diagrams') as $key => $diagram)
            @if(is_file($diagram['path']))
            <figure class="help-diagram"><figcaption><strong>{{ $diagram['label'] }}</strong></figcaption><a href="{{ route('help.workflow-image', $key) }}" target="_blank" rel="noopener"><img src="{{ route('help.workflow-image', $key) }}" alt="{{ $diagram['alt'] }}" loading="lazy"></a><p><a href="{{ route('help.workflow-image', $key) }}" target="_blank" rel="noopener">Open {{ $diagram['label'] }} diagram in a new tab</a></p></figure>
            @endif
        @endforeach
    @endif
    </article>
</div>
@endsection
