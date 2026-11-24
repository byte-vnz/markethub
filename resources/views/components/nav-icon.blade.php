<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('home')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>@break
@case('grid')<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>@break
@case('market')<path d="M3 9h18l-2-5H5L3 9zm1 0v11h16V9M9 20v-7h6v7"/>@break
@case('store')<path d="M3 9h18l-2-5H5L3 9zm1 0v11h16V9M8 14h8"/>@break
@case('users')<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2H3zm14-14a3 3 0 0 1 0 6m1 3a5 5 0 0 1 3 5"/>@break
@case('link')<path d="M10 13a5 5 0 0 0 7 .2l2-2a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7-.2l-2 2a5 5 0 0 0 7 7l1-1"/>@break
@case('receipt')<path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3zm4 5h6M9 12h6"/>@break
@case('payment')<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20m-16 5h5"/>@break
@case('history')<path d="M3 12a9 9 0 1 0 3-6M3 4v5h5m4-2v5l3 2"/>@break
@case('balance')<path d="M3 6h18v13H3zM3 10h18m-6 5h3"/>@break
@case('chart')<path d="M4 20V10m5 10V4m5 16v-7m5 7V8M2 20h20"/>@break
@case('shield')<path d="m12 2 8 3v6c0 5-3 9-8 11-5-2-8-6-8-11V5l8-3zm-3 9 2 2 4-4"/>@break
@case('settings')<circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M19 5l-2 2M7 17l-2 2"/>@break
@case('logout')<path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4m4-4 4-4-4-4m4 4H9"/>@break
@endswitch
</svg>
