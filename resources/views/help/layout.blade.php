@extends('layouts.app')
@section('title', $heading ?? 'Help & Support')
@section('subtitle', 'Market Stall Management System · User Manual v' . config('help.version', '1.0') . ' · Updated ' . config('help.updated', '2026-11-17'))
@section('content')
<style>
.help-nav{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.25rem}.help-nav a{min-height:44px;display:inline-flex;align-items:center;padding:.65rem 1rem;border:1px solid #cbd5e1;border-radius:.5rem;background:white;color:#1e3a8a;font-weight:600;font-size:.875rem}.help-nav a[aria-current=page]{background:#eff6ff;border-color:#1d67d7}.help-reading{display:grid;gap:1.25rem;align-items:start}.help-toc{max-height:65vh;overflow-y:auto}.help-toc a{display:block;padding:.6rem 0;border-bottom:1px solid #e2e8f0;font-size:.875rem;color:#1e3a8a}.help-prose{line-height:1.75;overflow-wrap:anywhere;min-width:0}.help-prose>*+*{margin-top:1rem}.help-prose h1{font-size:1.5rem}.help-prose h2{font-size:1.125rem;margin-top:2.25rem;scroll-margin-top:1.5rem}.help-prose h3{font-size:1rem;margin-top:1.75rem;scroll-margin-top:1.5rem}.help-prose h4{font-weight:600;margin-top:1.5rem}.help-prose ul{list-style:disc;padding-left:1.5rem}.help-prose ol{list-style:decimal;padding-left:1.5rem}.help-prose li+li{margin-top:.4rem}.help-prose a{color:#1e40af;text-decoration:underline}.help-table-scroll{position:relative;overflow-x:auto}.help-prose table{font-size:.875rem;border-collapse:collapse}.help-prose th,.help-prose td{padding:.65rem .8rem;border:1px solid #cbd5e1;text-align:left;vertical-align:top}.help-prose th{background:#f8fafc}.help-prose code{font-size:.875em;background:#f1f5f9;padding:.1rem .25rem}.help-prose pre{overflow-x:auto;white-space:pre-wrap;background:#f8fafc;padding:1rem;border:1px solid #e2e8f0;border-radius:.5rem}.help-prose blockquote{padding:.75rem 1rem;background:#eff6ff}.help-prose img{max-width:100%;height:auto}.help-prose hr{margin:2rem 0}.help-diagram{margin-top:1.5rem;border-top:1px solid #e2e8f0;padding-top:1.5rem}.help-diagram img{max-width:100%;height:auto;margin-top:1rem}
@media(min-width:1280px){.help-reading.with-toc{grid-template-columns:18rem minmax(0,1fr)}.help-reading.with-toc .help-toc{position:sticky;top:1.25rem}}
@media print{.help-nav,.help-toc,.help-downloads{display:none!important}.help-reading.with-toc{display:block}.help-table-scroll{overflow:visible}.help-prose h2,.help-prose h3{break-after:avoid}.help-prose tr{break-inside:avoid}}
</style>
<nav class="help-nav" aria-label="Help & Support sections">
    @foreach(['help.index' => 'Help home', 'help.manual' => 'User Manual', 'help.quick' => 'Quick Reference', 'help.workflows' => 'System Workflows', 'help.faq' => 'FAQs'] as $route => $label)
        <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@yield('help-content')
<section class="panel mt-6 help-downloads" aria-labelledby="help-download-heading">
    <h2 id="help-download-heading">Downloads &amp; PDF preview</h2>
    <p class="text-sm text-slate-600 mt-2">Version {{ config('help.version', '1.0') }} · {{ config('help.updated', '2026-11-17') }}. Downloads remain available only while signed in.</p>
    <ul class="mt-4 space-y-3">
    @foreach($downloads as $key => $file)
        <li class="flex flex-wrap items-center gap-3 text-sm">
        @if(is_file($file['path']) && is_readable($file['path']))
            <a class="btn secondary" href="{{ route('help.download', $key) }}">Download {{ $file['label'] }}</a>
            @if($file['mime'] === 'application/pdf')<a class="text-blue-800 underline" href="{{ route('help.preview', $key) }}" target="_blank" rel="noopener">Preview {{ $file['label'] }} <span class="sr-only">(opens in a new tab)</span></a>@endif
        @else
            <span class="font-semibold">{{ $file['label'] }}</span><span class="text-slate-600">Not available yet — read the online guide above.</span>
        @endif
        </li>
    @endforeach
    </ul>
</section>
@endsection
