{{-- Portal pelanggan ALMARA Billing WiFi (PWA). @2026-Alifiandra --}}
@php
  $t = $portalTenant;
  $acc = auth('customer')->user();
  $nav = [['Beranda', 'portal.home', 'home'], ['Tagihan', 'portal.invoices', 'receipt'], ['Bayar', 'portal.payments', 'wallet'], ['Bantuan', 'portal.tickets.index', 'ticket'], ['Profil', 'portal.profile', 'user']];
@endphp
<!doctype html>
<html lang="id" data-theme="{{ $t->theme === 'dark' ? 'dark' : 'light' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0c1a2e">
  <title>@yield('title', 'Portal') · {{ $t->displayName() }}</title>
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('almara.version') }}">
  <style>
    .portal { max-width: 760px; margin: 0 auto; padding: 1rem 1rem 6rem; }
    .portal-top { display: flex; align-items: center; gap: .7rem; padding: .9rem 1rem; background: var(--surface); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 20; }
    .portal-top .brand-mark { width: 34px; height: 34px; }
    .portal-nav { display: flex; position: fixed; bottom: 0; left: 0; right: 0; background: var(--surface); border-top: 1px solid var(--border); z-index: 30; padding: .35rem .25rem calc(.35rem + env(safe-area-inset-bottom)); justify-content: center; }
    .portal-nav a { flex: 1; max-width: 150px; display: flex; flex-direction: column; align-items: center; gap: .15rem; font-size: .7rem; color: var(--text-3); padding: .3rem 0; }
    .portal-nav a.active { color: var(--primary); }
    .portal-nav svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .hero { background: linear-gradient(135deg, #0b6bcb, #0ea5a4); color: #fff; border-radius: 16px; padding: 1.2rem 1.3rem; }
    .hero .muted { color: rgba(255,255,255,.8); }
    .list-item { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .85rem 1.1rem; border-bottom: 1px solid var(--border); color: var(--text); }
    .list-item:last-child { border-bottom: 0; }
    .bubble { padding: .75rem .9rem; border-radius: 12px; margin-bottom: .6rem; max-width: 88%; white-space: pre-wrap; word-break: break-word; }
    .bubble.me { background: var(--primary-50); margin-left: auto; }
    .bubble.them { background: var(--surface-2); border: 1px solid var(--border); }
    .bubble.sys { background: transparent; text-align: center; max-width: 100%; color: var(--text-3); font-size: .8rem; padding: .2rem; }
  </style>
</head>
<body>
@auth('customer')
<header class="portal-top">
  <div class="brand-mark">@if ($t->logo_path)<img src="{{ asset('storage/'.$t->logo_path) }}" alt="">@else A @endif</div>
  <div style="flex:1;min-width:0"><div style="font-weight:700">{{ $t->displayName() }}</div><div class="small muted">Portal pelanggan</div></div>
  <form method="POST" action="{{ route('portal.logout', $t->code) }}">@csrf<button class="btn btn-sm btn-ghost" title="Keluar"><x-icon name="logout" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8"/></button></form>
</header>
@endauth
<main class="portal">
  @include('partials.flash')
  @yield('content')
  <p class="small faint center" style="margin-top:2rem">Didukung ALMARA Billing WiFi · {{ config('almara.signature') }}</p>
</main>
@auth('customer')
<nav class="portal-nav">
  @foreach ($nav as [$label, $route, $icon])
    <a href="{{ route($route, $t->code) }}" class="{{ request()->routeIs($route) || request()->routeIs($route.'.*') || ($route === 'portal.tickets.index' && request()->routeIs('portal.tickets.*')) || ($route === 'portal.invoices' && request()->routeIs('portal.invoices.*')) ? 'active' : '' }}"><x-icon :name="$icon"/>{{ $label }}</a>
  @endforeach
</nav>
@endauth
<script src="{{ asset('js/password-toggle.js') }}?v={{ config('almara.version') }}"></script>
@if (config('almara.pwa'))
<script>if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(function(){});</script>
@endif
</body>
</html>
