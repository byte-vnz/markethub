@php($navGroups = \App\Support\Navigation::groups(auth()->user(), request()))
<div class="sidebar-overlay" data-sidebar-overlay hidden></div>
<aside id="sidebar" class="sidebar-shell" aria-label="Application sidebar">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="sidebar-brand-link" title="MarketHub dashboard" aria-label="MarketHub dashboard"><span class="sidebar-mark">MH</span><span class="sidebar-brand-text"><strong>MarketHub</strong><small>LGU market administration</small></span></a>
        <button type="button" class="sidebar-close" data-sidebar-close aria-label="Close navigation">&times;</button>
    </div>
    <nav class="sidebar-nav" aria-label="Main navigation">
        @foreach($navGroups as $group)
        <details class="nav-group" data-nav-group="{{ $group['key'] }}" @if($group['current']) open @endif>
            <summary class="nav-group-heading" title="{{ $group['label'] }}" aria-controls="nav-{{ $group['key'] }}">
                @include('components.nav-icon', ['name' => $group['icon']])
                <span class="nav-group-label">{{ $group['label'] }}</span><span class="nav-chevron" aria-hidden="true">⌄</span>
            </summary>
            <div class="nav-group-items" id="nav-{{ $group['key'] }}">
                @foreach($group['items'] as $item)
                <a href="{{ $item['url'] }}" class="navlink {{ $item['current'] ? 'active' : '' }}" title="{{ $item['label'] }}" @if($item['current']) aria-current="page" @endif>
                    @include('components.nav-icon', ['name' => $item['icon']])<span class="nav-item-label">{{ $item['label'] }}</span>
                </a>
                @endforeach
            </div>
        </details>
        @endforeach
    </nav>
    <div class="sidebar-account">
        <span class="sidebar-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
        <div class="sidebar-account-text"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->getRoleNames()->join(', ') }}</small></div>
        <form method="post" action="{{ route('logout') }}" class="sidebar-logout">@csrf<button type="submit" title="Sign out" aria-label="Sign out">@include('components.nav-icon', ['name' => 'logout'])<span class="nav-item-label">Sign out</span></button></form>
    </div>
</aside>
