{{-- ALMARA Billing WiFi SaaS — layout utama. @2026-Alifiandra --}}
@php
  $user = auth()->user();
  $isAdmin = $user->isSuperAdmin();
  $brand = $isAdmin ? 'ALMARA Billing' : ($currentTenant?->displayName() ?? config('app.name'));
  $theme = $user->theme_preference ?: ($currentTenant?->theme && $currentTenant->theme !== 'default' ? $currentTenant->theme : null);
  $nav = $isAdmin ? [
    ['Platform', null, null, null],
    ['Dashboard', 'admin.dashboard', 'home', 'platform.dashboard'],
    ['Manajemen Supplier ISP', 'admin.tenants.index', 'building', 'tenants.view'],
    ['Paket SaaS', 'admin.plans.index', 'layers', 'saas_plans.manage'],
    ['Tagihan SaaS', 'admin.saas-invoices.index', 'receipt', 'saas_subscriptions.manage'],
    ['Audit Platform', 'admin.audit.index', 'shield', 'platform.audit_logs.view'],
  ] : [
    ['Utama', null, null, null],
    ['Dashboard', 'app.dashboard', 'home', 'dashboard.view'],
    ['Pelanggan', 'app.customers.index', 'users', 'customers.view'],
    ['Invoice', 'app.invoices.index', 'receipt', 'invoices.view'],
    ['Pembayaran', 'app.payments.index', 'wallet', 'payments.view'],
    ['Jaringan', null, null, null],
    ['Paket Layanan', 'app.packages.index', 'package', 'packages.manage'],
    ['Router & Perangkat', 'app.devices.index', 'router', 'monitoring.devices.view'],
    ['Pengaturan', null, null, null],
    ['WhatsApp', 'app.templates.index', 'message', 'notifications.manage'],
    ['Pengguna & Role', 'app.users.index', 'user', 'users.manage'],
    ['Pengaturan Umum', 'app.settings.edit', 'settings', 'settings.manage'],
    ['Audit Log', 'app.audit.index', 'shield', 'audit_logs.view'],
  ];
  $bottom = $isAdmin
    ? [['Dashboard','admin.dashboard','home'],['ISP','admin.tenants.index','building'],['Paket','admin.plans.index','layers'],['Tagihan','admin.saas-invoices.index','receipt']]
    : [['Beranda','app.dashboard','home'],['Pelanggan','app.customers.index','users'],['Invoice','app.invoices.index','receipt'],['Bayar','app.payments.index','wallet']];
  $isActive = fn ($r) => substr_count($r, '.') > 1 ? request()->routeIs(\Illuminate\Support\Str::beforeLast($r, '.').'.*') : request()->routeIs($r);
@endphp
<!doctype html>
<html lang="id" data-theme="{{ $theme ?? 'light' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#0c1a2e">
  <meta name="author" content="@2026-Alifiandra">
  <title>@yield('title', 'Dashboard') · {{ $brand }}</title>
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/icons/icon-192.png">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('almara.version') }}">
  <script>
    (function(){ try { var t = localStorage.getItem('almara-theme'); if (t && !@json((bool) $user->theme_preference)) document.documentElement.dataset.theme = t; } catch(e){} })();
  </script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
  @stack('head')
</head>
<body x-data="{ side: false }">
<div class="shell">
  <aside class="sidebar" :class="{ open: side }" @click.outside="side = false">
    <div class="brand">
      <div class="brand-mark">
        @if (! $isAdmin && $currentTenant?->logo_path)
          <img src="{{ asset('storage/'.$currentTenant->logo_path) }}" alt="">
        @else A @endif
      </div>
      <div style="min-width:0">
        <div class="brand-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $brand }}</div>
        <div class="brand-sub">{{ $isAdmin ? 'Super Admin' : 'Billing & Operasional ISP' }}</div>
      </div>
    </div>
    <nav class="nav">
      @foreach ($nav as [$label, $route, $icon, $perm])
        @if (! $route)
          <div class="nav-label">{{ $label }}</div>
        @elseif ($user->can($perm))
          <a href="{{ route($route) }}" class="{{ $isActive($route) ? 'active' : '' }}"><x-icon :name="$icon"/> {{ $label }}</a>
        @endif
      @endforeach
    </nav>
    <div class="sidebar-foot">ALMARA Billing WiFi v{{ config('almara.version') }}<br>{{ config('almara.signature') }}</div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" @click.stop="side = !side" aria-label="Menu"><x-icon name="menu"/></button>
      <div class="title">@yield('title', 'Dashboard')</div>
      <button class="icon-btn" type="button" aria-label="Ganti tema" x-data
        @click="let t = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = t; try { localStorage.setItem('almara-theme', t) } catch(e) {}">
        <x-icon name="moon"/>
      </button>
      <div class="dropdown" x-data="{ open: false }" @click.outside="open = false">
        <button class="user-chip" @click="open = !open" type="button">
          <span class="avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
          <span class="uname small">{{ $user->name }}</span>
        </button>
        <div class="dropdown-menu" x-show="open" x-cloak x-transition.opacity>
          <div class="small muted" style="padding:.4rem .7rem">{{ $user->email }}</div>
          <a href="{{ route('profile.edit') }}">Profil & password</a>
          <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form>
        </div>
      </div>
    </header>

    <main class="content">
      @include('partials.flash')
      @yield('content')
    </main>
    <footer class="footer">© {{ date('Y') }} ALMARA Billing WiFi · {{ config('almara.signature') }}</footer>
  </div>
</div>

<nav class="bottom-nav">
  @foreach ($bottom as [$label, $route, $icon])
    <a href="{{ route($route) }}" class="{{ $isActive($route) ? 'active' : '' }}"><x-icon :name="$icon"/>{{ $label }}</a>
  @endforeach
</nav>

@if (config('almara.pwa'))
<script>
  if ('serviceWorker' in navigator) { window.addEventListener('load', function(){ navigator.serviceWorker.register('/sw.js').catch(function(){}); }); }
</script>
@else
<script>
  {{-- Mode development: PWA dimatikan & service worker ALMARA lama dilepas agar tidak bentrok dengan proyek lain di alamat yang sama. --}}
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(function (regs) {
      regs.forEach(function (r) { var s = r.active || r.waiting || r.installing; if (s && s.scriptURL === location.origin + '/sw.js') r.unregister(); });
    });
    if (window.caches) caches.keys().then(function (k) { k.filter(function (n) { return n.indexOf('almara-static-') === 0; }).forEach(function (n) { caches.delete(n); }); });
  }
</script>
@endif
@stack('scripts')
<script src="{{ asset('js/password-toggle.js') }}?v={{ config('almara.version') }}"></script>
</body>
</html>
