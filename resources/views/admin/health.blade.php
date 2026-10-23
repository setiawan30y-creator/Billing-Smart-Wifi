@extends('layouts.app')
@section('title', 'Kesehatan Platform')
@section('content')
@php $hbOk = $heartbeat && $heartbeat->gt(now()->subMinutes(3)); @endphp
<div class="page-head"><div><h1>Kesehatan Platform</h1><p>Kondisi sistem SaaS — terpisah dari monitoring jaringan milik ISP.</p></div></div>
<div class="grid grid-4" style="margin-bottom:1rem">
  <div class="card stat"><div class="stat-label">Scheduler</div><div class="stat-value" style="color:{{ $hbOk ? 'var(--success)' : 'var(--danger)' }};font-size:1.1rem">{{ $hbOk ? 'Berjalan' : 'Tidak terdeteksi' }}</div><div class="stat-sub">{{ $heartbeat ? 'detak terakhir '.$heartbeat->diffForHumans() : 'jalankan schedule:work / cron schedule:run' }}</div></div>
  <div class="card stat"><div class="stat-label">Antrean ({{ $queue['driver'] }})</div><div class="stat-value">{{ $queue['pending'] }}</div><div class="stat-sub">{{ $queue['failed'] }} job gagal{{ $queue['oldest'] !== null ? ' · tertua '.$queue['oldest'].' menit' : '' }}</div></div>
  <div class="card stat"><div class="stat-label">Integrasi (24 jam)</div><div class="stat-value" style="font-size:1.1rem">{{ $integrations['wa_failed'] }} WA gagal</div><div class="stat-sub">{{ $integrations['wa_queued_old'] }} WA tertahan >30 mnt · {{ $integrations['webhook_unmatched'] }} webhook unmatched</div></div>
  <div class="card stat"><div class="stat-label">Collector monitoring (24 jam)</div><div class="stat-value" style="font-size:1.1rem">{{ $integrations['collector_errors'] }} error</div><div class="stat-sub">{{ $integrations['collector_failed_runs'] }} run gagal · {{ number_format($samples, 0, ',', '.') }} sampel tersimpan</div></div>
</div>
@if ($backups['failed'])<div class="alert alert-danger">{{ $backups['failed'] }} backup gagal dalam 7 hari terakhir. <a href="{{ route('admin.backups.index') }}">Periksa</a></div>@endif
<div class="card"><div class="card-head"><h3>Kapasitas & status per ISP</h3></div><div class="table-wrap"><table class="table">
  <thead><tr><th>ISP</th><th>Paket</th><th>Client aktif</th><th>MikroTik</th><th>Backup terakhir</th><th>Collector terakhir</th></tr></thead><tbody>
  @foreach ($tenants as $row)
    @php $t = $row['tenant']; $q = $row['quota']; @endphp
    <tr><td><a href="{{ route('admin.tenants.show', $t) }}">{{ $t->name }}</a> <x-badge :status="$t->status" :label="$t->statusLabel()"/></td><td class="small">{{ $t->currentSubscription?->plan?->name ?? '-' }}</td>
      @foreach (['clients', 'routers'] as $k)<td class="small"><strong style="{{ $q[$k]['level'] === 'over' ? 'color:var(--danger)' : ($q[$k]['level'] === 'warn' ? 'color:var(--warning)' : '') }}">{{ $q[$k]['used'] }}</strong> / {{ $q[$k]['quota'] ?? '∞' }}</td>@endforeach
      <td class="small">@if ($row['last_backup']){{ \Carbon\Carbon::parse($row['last_backup'])->diffForHumans() }}@else<span style="color:var(--danger)">belum pernah</span>@endif</td>
      <td class="small">{{ $row['last_run'] ? $row['last_run']->started_at->diffForHumans().($row['last_run']->collector_errors ? ' · '.$row['last_run']->collector_errors.' error' : '') : '-' }}</td></tr>
  @endforeach
</tbody></table></div></div>
@endsection
