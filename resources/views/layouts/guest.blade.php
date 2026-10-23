{{-- @2026-Alifiandra --}}
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0c1a2e">
  <title>@yield('title', 'Masuk') · {{ config('app.name') }}</title>
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('almara.version') }}">
  <script>(function(){ try { var t = localStorage.getItem('almara-theme'); if (t) document.documentElement.dataset.theme = t; } catch(e){} })();</script>
</head>
<body>
<div class="auth">
  <section class="auth-hero">
    <div class="brand" style="padding:0;border:0"><div class="brand-mark">A</div><div><div class="brand-name">ALMARA Billing WiFi</div><div class="brand-sub">Platform SaaS untuk ISP</div></div></div>
    <div>
      <h1>Billing, pembayaran & operasional ISP dalam satu platform.</h1>
      <ul>
        <li>Multi-tenant dengan isolasi data per ISP</li>
        <li>Invoice otomatis & pengingat WhatsApp</li>
        <li>Verifikasi pembayaran & audit keuangan</li>
        <li>Registry router & perangkat jaringan</li>
      </ul>
    </div>
    <div class="small" style="opacity:.6">{{ config('almara.signature') }}</div>
  </section>
  <section class="auth-form">
    <div class="auth-card">@yield('content')</div>
  </section>
</div>
</body>
</html>
