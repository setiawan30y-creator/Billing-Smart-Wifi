@extends('layouts.app')
@section('title', 'PPPoE MikroTik')
@section('content')
{{-- PPPoE MikroTik: secret & sesi online (read-only). @2026-Alifiandra --}}
@php
  use App\Integrations\MikroTik\PppoeReader;
  use App\Models\CustomerService;
  $base = ['device' => $device?->id];
@endphp
<div class="page-head"><div><h1>PPPoE MikroTik</h1><p>PPP secret dan pelanggan yang sedang online, dibaca langsung dari router (read-only).</p></div>
  @if ($device)<div class="actions"><a class="btn" href="{{ route('app.pppoe.index', $base + ['tab' => $tab, 'refresh' => 1]) }}"><x-icon name="refresh"/> Muat ulang</a></div>@endif</div>

@if ($routers->isEmpty())
  <div class="card"><div class="empty">Belum ada router MikroTik aktif.
    @can('monitoring.devices.manage')<div style="margin-top:.6rem"><a class="btn btn-primary" href="{{ route('app.devices.create') }}"><x-icon name="plus"/> Tambah router</a></div>@endcan</div></div>
@else
  <div class="card" style="margin-bottom:1rem">
    <form class="toolbar" method="GET" style="border-bottom:0">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <label class="small muted" for="device">Router</label>
      <select id="device" name="device" class="input" onchange="this.form.submit()">
        @foreach ($routers as $r)<option value="{{ $r->id }}" @selected($device?->id === $r->id)>{{ $r->name }} — {{ $r->host }}</option>@endforeach
      </select>
      @if ($cachedAt)<span class="small faint">Data diambil {{ \Illuminate\Support\Carbon::parse($cachedAt)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }} · disimpan sementara {{ \App\Http\Controllers\Isp\PppoeController::CACHE_SECONDS }} detik</span>@endif
    </form>
  </div>

  @if ($error)
    <div class="alert alert-danger"><strong>Gagal membaca router:</strong> {{ $error }}
      <div class="small" style="margin-top:.35rem">Periksa: IP/port API (default 8728) dapat dijangkau dari server, layanan <span class="mono">api</span> aktif di <span class="mono">/ip service</span>, serta username & password API di menu Router & Perangkat. User dengan group <span class="mono">read</span> sudah cukup.</div></div>
  @else
    <div class="grid grid-4" style="margin-bottom:1rem">
      <div class="card stat"><div class="stat-label">PPP secret</div><div class="stat-value">{{ number_format($stats['secrets'], 0, ',', '.') }}</div><div class="stat-sub">{{ $stats['disabled'] }} dinonaktifkan</div></div>
      <div class="card stat"><div class="stat-label">Online</div><div class="stat-value" style="color:var(--success)">{{ number_format($stats['online'], 0, ',', '.') }}</div><div class="stat-sub">sesi PPP aktif</div></div>
      <div class="card stat"><div class="stat-label">Offline</div><div class="stat-value">{{ number_format($stats['offline'], 0, ',', '.') }}</div><div class="stat-sub">secret aktif tanpa sesi</div></div>
      <div class="card stat"><div class="stat-label">Belum terhubung ke pelanggan</div><div class="stat-value">{{ number_format($stats['unlinked'], 0, ',', '.') }}</div><div class="stat-sub">@if ($stats['isolated_online'])<span style="color:var(--danger)">{{ $stats['isolated_online'] }} terisolir tapi online</span>@else username PPPoE belum dipasang @endif</div></div>
    </div>

    <div class="card">
      <div class="tabs">
        <a href="{{ route('app.pppoe.index', $base + ['tab' => 'secrets']) }}" @class(['active' => $tab === 'secrets'])>Secret <span class="badge badge-muted">{{ $stats['secrets'] }}</span></a>
        <a href="{{ route('app.pppoe.index', $base + ['tab' => 'active']) }}" @class(['active' => $tab === 'active'])>Online <span class="badge badge-success">{{ $stats['online'] }}</span></a>
      </div>
      <form class="toolbar" method="GET">
        <input type="hidden" name="device" value="{{ $device->id }}"><input type="hidden" name="tab" value="{{ $tab }}">
        <input class="input grow" name="q" value="{{ request('q') }}" placeholder="Cari username, pelanggan, IP, MAC, atau komentar">
        <select name="filter" class="input"><option value="">Semua</option>
          @if ($tab === 'secrets')
            <option value="online" @selected(request('filter') === 'online')>Online</option>
            <option value="offline" @selected(request('filter') === 'offline')>Offline</option>
            <option value="disabled" @selected(request('filter') === 'disabled')>Dinonaktifkan</option>
          @endif
          <option value="unlinked" @selected(request('filter') === 'unlinked')>Belum terhubung ke pelanggan</option>
          <option value="isolated" @selected(request('filter') === 'isolated')>Layanan terisolir</option>
        </select>
        <button class="btn"><x-icon name="search"/> Cari</button>
      </form>

      <div class="table-wrap"><table class="table">
      @if ($tab === 'secrets')
        <thead><tr><th>Username</th><th>Pelanggan</th><th>Profile</th><th>Remote address</th><th>Status</th><th>Terakhir</th></tr></thead>
        <tbody>
        @forelse ($rows as $s)
          <tr>
            <td><span class="mono"><strong>{{ $s['name'] }}</strong></span>@if ($s['comment'])<div class="small muted">{{ \Illuminate\Support\Str::limit($s['comment'], 60) }}</div>@endif</td>
            <td>@include('isp.pppoe._customer', ['svc' => $s['service_row']])</td>
            <td class="small">{{ $s['profile'] ?: '-' }}<div class="faint">{{ $s['service'] }}</div></td>
            <td class="mono small">{{ $s['session']['address'] ?? $s['remote_address'] ?? '-' }}</td>
            <td>@if ($s['disabled'])<span class="badge badge-muted">Dinonaktifkan</span>
                @elseif ($s['session'])<span class="badge badge-success">● Online</span><div class="small faint">{{ PppoeReader::humanUptime($s['session']['uptime']) }}</div>
                @else<span class="badge badge-danger">Offline</span>@endif</td>
            <td class="small">@if ($s['session'])<span class="mono">{{ $s['session']['caller_id'] }}</span>
                @else{{ $s['last_logged_out'] && $s['last_logged_out'] !== 'never' ? $s['last_logged_out'] : '-' }}@if ($s['last_disconnect_reason'])<div class="faint">{{ $s['last_disconnect_reason'] }}</div>@endif @endif</td>
          </tr>
        @empty <tr><td colspan="6" class="empty">Tidak ada secret yang cocok.</td></tr> @endforelse
        </tbody>
      @else
        <thead><tr><th>Username</th><th>Pelanggan</th><th>IP address</th><th>Caller ID (MAC)</th><th>Uptime</th><th>Service</th></tr></thead>
        <tbody>
        @forelse ($rows as $a)
          <tr>
            <td><span class="mono"><strong>{{ $a['name'] }}</strong></span>@unless ($a['has_secret'])<div class="small faint">tanpa secret lokal (RADIUS?)</div>@endunless</td>
            <td>@include('isp.pppoe._customer', ['svc' => $a['service_row']])</td>
            <td class="mono small">{{ $a['address'] ?: '-' }}</td>
            <td class="mono small">{{ $a['caller_id'] ?: '-' }}</td>
            <td class="small nowrap">{{ PppoeReader::humanUptime($a['uptime']) }}</td>
            <td class="small">{{ $a['service'] ?: '-' }}@if ($a['encoding'])<div class="faint">{{ $a['encoding'] }}</div>@endif</td>
          </tr>
        @empty <tr><td colspan="6" class="empty">Tidak ada sesi online yang cocok.</td></tr> @endforelse
        </tbody>
      @endif
      </table></div>
      {{ $rows->links() }}
    </div>
    <p class="small faint" style="margin-top:.8rem">Pelanggan dicocokkan dari kolom <em>Username PPPoE</em> pada layanan pelanggan. Password secret tidak pernah dibaca dari router. Halaman ini tidak mengubah konfigurasi router.</p>
  @endif
@endif
@endsection
