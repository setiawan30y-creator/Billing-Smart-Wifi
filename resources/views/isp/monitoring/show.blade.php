@extends('layouts.app')
@section('title', $target->name)
@section('content')
@php
  $fmtBps = fn ($v) => $v === null ? '—' : ($v >= 1e9 ? round($v / 1e9, 2).' Gbps' : ($v >= 1e6 ? round($v / 1e6, 2).' Mbps' : round($v / 1e3, 1).' kbps'));
  $pts = fn ($key) => $samples->map(fn ($s) => [$s->measured_at->format('d/m H:i'), $s->status === 'error' ? null : ($s->$key !== null ? (float) $s->$key : null)])->values()->all();
  $last = $samples->last();
@endphp
<div class="page-head">
  <div><h1>{{ $target->name }} <x-badge :status="$target->statusBadge()" :label="\App\Models\MonitoringTarget::STATUSES[$target->last_status]"/></h1>
    <p class="mono">{{ $target->host }} · {{ strtoupper($target->method) }} · tiap {{ $target->interval_minutes }} menit · sampel terakhir {{ $target->last_checked_at?->format('d/m/Y H:i:s') ?? '-' }}</p></div>
  <div class="actions">
    @foreach ([6 => '6 jam', 24 => '24 jam', 72 => '3 hari', 168 => '7 hari'] as $h => $l)<a href="{{ route('app.monitoring.show', [$target, 'h' => $h]) }}" class="badge badge-{{ $hours === $h ? 'primary' : 'muted' }}">{{ $l }}</a>@endforeach
    @can('monitoring.settings.manage')<form method="POST" action="{{ route('app.monitoring.check', $target) }}">@csrf<button class="btn"><x-icon name="refresh"/> Cek sekarang</button></form><a href="{{ route('app.monitoring.edit', $target) }}" class="btn">Ubah</a>@endcan
  </div>
</div>
@if ($target->isStale())<div class="alert alert-warning">Data basi: tidak ada sampel baru lebih dari {{ $target->interval_minutes * 3 }} menit. Periksa scheduler/collector.</div>@endif
<div class="grid grid-4" style="margin-bottom:1rem">
  <div class="card stat"><div class="stat-label">Ketersediaan ({{ $hours }} jam)</div><div class="stat-value">{{ $uptimePct !== null ? $uptimePct.'%' : '—' }}</div><div class="stat-sub">{{ $samples->count() }} sampel · {{ $samples->where('status', 'error')->count() }} error collector</div></div>
  <div class="card stat"><div class="stat-label">Latensi terakhir</div><div class="stat-value">{{ $target->last_latency_ms !== null ? (float) $target->last_latency_ms.' ms' : '—' }}</div><div class="stat-sub">sumber {{ $last?->source ?? '-' }}</div></div>
  <div class="card stat"><div class="stat-label">Packet loss terakhir</div><div class="stat-value">{{ $target->last_loss_pct !== null ? (float) $target->last_loss_pct.'%' : '—' }}</div></div>
  <div class="card stat"><div class="stat-label">Perangkat</div>@if ($last?->cpu_load_pct !== null)<div class="stat-value" style="font-size:1.1rem">CPU {{ (float) $last->cpu_load_pct }}% · RAM {{ (float) $last->memory_used_pct }}%</div><div class="stat-sub">PPPoE aktif {{ $last->pppoe_active ?? '—' }} · uptime {{ $last->uptime_seconds ? \Carbon\CarbonInterval::seconds($last->uptime_seconds)->cascade()->forHumans(['short' => true, 'parts' => 2]) : '—' }}</div>@else<div class="stat-value" style="font-size:1rem">Tidak diukur</div><div class="stat-sub">butuh metode RouterOS</div>@endif</div>
</div>
<div class="card" style="margin-bottom:1rem"><div class="card-head"><h3>Latensi</h3><span class="small faint">Garis terputus = tidak ada data terukur</span></div><div class="card-body"><x-line-chart :points="$pts('latency_ms')" unit="ms"/></div></div>
@if ($samples->whereNotNull('rx_bps')->count())
<div class="grid grid-2" style="margin-bottom:1rem">
  <div class="card"><div class="card-head"><h3>Download (rx) {{ $target->interface_name }}</h3></div><div class="card-body"><x-line-chart :points="$samples->map(fn ($s) => [$s->measured_at->format('d/m H:i'), $s->rx_bps !== null ? round($s->rx_bps / 1e6, 2) : null])->values()->all()" unit="Mbps" color="var(--accent)"/></div></div>
  <div class="card"><div class="card-head"><h3>Upload (tx) {{ $target->interface_name }}</h3></div><div class="card-body"><x-line-chart :points="$samples->map(fn ($s) => [$s->measured_at->format('d/m H:i'), $s->tx_bps !== null ? round($s->tx_bps / 1e6, 2) : null])->values()->all()" unit="Mbps" color="var(--warning)"/></div></div>
</div>
@endif
@if ($samples->whereNotNull('cpu_load_pct')->count())
<div class="card" style="margin-bottom:1rem"><div class="card-head"><h3>CPU (%)</h3></div><div class="card-body"><x-line-chart :points="$pts('cpu_load_pct')" unit="%" color="var(--danger)"/></div></div>
@endif
<div class="grid grid-main">
  <div class="card"><div class="card-head"><h3>Sampel terbaru</h3></div><div class="table-wrap"><table class="table">
    <thead><tr><th>Waktu</th><th>Status</th><th class="num">Latensi</th><th class="num">Loss</th><th class="num">rx / tx</th><th>Catatan</th></tr></thead><tbody>
    @forelse ($recent as $s)<tr><td class="small nowrap">{{ $s->measured_at->format('d/m H:i:s') }}</td><td><x-badge :status="['up' => 'active', 'down' => 'failed', 'error' => 'muted'][$s->status]" :label="['up' => 'up', 'down' => 'down', 'error' => 'error collector'][$s->status]"/></td>
      <td class="num">{{ $s->latency_ms !== null ? (float) $s->latency_ms : '—' }}</td><td class="num">{{ $s->packet_loss_pct !== null ? (float) $s->packet_loss_pct.'%' : '—' }}</td>
      <td class="num small">{{ $fmtBps($s->rx_bps) }} / {{ $fmtBps($s->tx_bps) }}</td><td class="small faint">{{ \Illuminate\Support\Str::limit($s->error, 60) }}</td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada sampel.</td></tr> @endforelse
  </tbody></table></div></div>
  <div class="card"><div class="card-head"><h3>Riwayat alarm</h3></div><div class="card-body">
    @forelse ($alerts as $a)<div class="small" style="margin-bottom:.6rem"><x-badge :status="$a->status === 'resolved' ? 'done' : ($a->severity === 'critical' ? 'failed' : 'pending')" :label="$a->status === 'resolved' ? 'pulih' : $a->status"/> {{ $a->message }}<div class="faint">{{ $a->started_at->format('d/m H:i') }}{{ $a->resolved_at ? ' → '.$a->resolved_at->format('d/m H:i') : '' }}</div></div>
    @empty <p class="small muted">Tidak ada alarm.</p> @endforelse
  </div></div>
</div>
@endsection
