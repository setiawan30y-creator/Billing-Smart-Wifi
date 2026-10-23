@extends('layouts.app')
@section('title', 'Monitoring Jaringan')
@section('content')
@php $c = $targets->countBy('last_status'); @endphp
<div class="page-head"><div><h1>Monitoring Jaringan</h1><p>Read-only: hanya menampilkan data yang benar-benar terukur, lengkap dengan waktu sampel.</p></div>
  <div class="actions"><a href="{{ route('app.monitoring.alerts') }}" class="btn"><x-icon name="alert"/> Alarm</a>@can('monitoring.settings.manage')<a href="{{ route('app.monitoring.create') }}" class="btn btn-primary"><x-icon name="plus"/> Target</a>@endcan</div></div>

<div class="grid grid-4" style="margin-bottom:1rem">
  <div class="card stat"><div class="stat-label">Normal</div><div class="stat-value" style="color:var(--success)">{{ $c['up'] ?? 0 }}</div></div>
  <div class="card stat"><div class="stat-label">Menurun</div><div class="stat-value" style="color:var(--warning)">{{ $c['degraded'] ?? 0 }}</div></div>
  <div class="card stat"><div class="stat-label">Tidak terjangkau</div><div class="stat-value" style="color:var(--danger)">{{ $c['down'] ?? 0 }}</div></div>
  <div class="card stat"><div class="stat-label">Collector</div>
    @if (! $lastRun)<div class="stat-value" style="font-size:1rem">Belum berjalan</div><div class="stat-sub">Jalankan scheduler / <span class="mono">php artisan monitoring:collect</span></div>
    @else<div class="stat-value" style="font-size:1rem">{{ $lastRun->started_at->diffForHumans() }}</div><div class="stat-sub">{{ $failedRuns ? $failedRuns.' run dengan error collector (24 jam)' : 'tanpa error collector (24 jam)' }}</div>@endif
  </div>
</div>
@if ($lastRun && $lastRun->started_at->lt(now()->subMinutes(10)))
  <div class="alert alert-warning">Collector tidak berjalan sejak {{ $lastRun->started_at->format('d/m H:i') }}. Data di bawah mungkin basi — ini gangguan sistem monitoring, bukan berarti jaringan bermasalah.</div>
@endif

@if ($alerts->count())
<div class="card" style="margin-bottom:1rem"><div class="card-head"><h3>Alarm aktif</h3></div><div class="table-wrap"><table class="table"><tbody>
  @foreach ($alerts as $a)
    <tr><td><x-badge :status="$a->severity === 'critical' ? 'failed' : 'pending'" :label="$a->severity === 'critical' ? 'Kritis' : 'Peringatan'"/></td><td><a href="{{ route('app.monitoring.show', $a->monitoring_target_id) }}">{{ $a->message }}</a><div class="small faint">sejak {{ $a->started_at->format('d/m H:i') }} · {{ $a->status === 'acknowledged' ? 'di-acknowledge' : 'belum ditangani' }}</div></td>
      <td class="right">@can('monitoring.alerts.acknowledge')@if ($a->status === 'open')<form method="POST" action="{{ route('app.monitoring.ack', $a) }}">@csrf<button class="btn btn-sm">Acknowledge</button></form>@endif @endcan</td></tr>
  @endforeach
</tbody></table></div></div>
@endif

<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Target</th><th>Metode</th><th>Status</th><th class="num">Latensi</th><th class="num">Loss</th><th>Sampel terakhir</th></tr></thead>
  <tbody>
  @forelse ($targets as $t)
    <tr><td><a href="{{ route('app.monitoring.show', $t) }}"><strong>{{ $t->name }}</strong></a><div class="small mono faint">{{ $t->host }}{{ $t->port && $t->method === 'tcp' ? ':'.$t->port : '' }}</div></td>
      <td class="small">{{ strtoupper($t->method) }}@unless ($t->enabled)<div><x-badge status="void" label="nonaktif"/></div>@endunless</td>
      <td><x-badge :status="$t->statusBadge()" :label="\App\Models\MonitoringTarget::STATUSES[$t->last_status]"/>@if ($t->isStale())<div><x-badge status="muted" label="data basi"/></div>@endif</td>
      <td class="num">{{ $t->last_latency_ms !== null ? (float) $t->last_latency_ms.' ms' : '—' }}</td>
      <td class="num">{{ $t->last_loss_pct !== null ? (float) $t->last_loss_pct.'%' : '—' }}</td>
      <td class="small">{{ $t->last_checked_at?->diffForHumans() ?? 'belum pernah' }}@if ($t->last_error)<div class="faint" title="{{ $t->last_error }}">{{ \Illuminate\Support\Str::limit($t->last_error, 50) }}</div>@endif</td></tr>
  @empty <tr><td colspan="6" class="empty">Belum ada target monitoring.</td></tr> @endforelse
  </tbody></table></div></div>

@if ($unmonitored->count())
<div class="card" style="margin-top:1rem"><div class="card-head"><h3>Perangkat belum dipantau</h3></div><div class="card-body actions">
  @foreach ($unmonitored as $d)@can('monitoring.settings.manage')<a href="{{ route('app.monitoring.create', ['device' => $d->id]) }}" class="btn btn-sm">+ {{ $d->name }}</a>@else<span class="badge badge-muted">{{ $d->name }}</span>@endcan @endforeach
</div></div>
@endif

@can('monitoring.settings.manage')
<div class="card" style="margin-top:1rem;max-width:760px"><div class="card-head"><h3>Ambang deteksi gangguan massal</h3></div>
  <form method="POST" action="{{ route('app.monitoring.settings') }}" class="card-body form-grid">@csrf
    <x-field name="ticket_threshold" label="Jumlah pelanggan melapor gangguan (per ODP)" type="number" :value="auth()->user()->tenant->setting('incident.ticket_threshold', 3)" min="2" required/>
    <x-field name="window_minutes" label="Dalam rentang waktu (menit)" type="number" :value="auth()->user()->tenant->setting('incident.window_minutes', 60)" min="10" required/>
    <p class="full small muted" style="margin:0">Bila terlampaui, sistem membuat insiden berstatus Investigasi (tanpa mengirim pengumuman otomatis). Penyebab tidak diasumsikan.</p>
    <div><button class="btn">Simpan</button></div>
  </form></div>
@endcan
@endsection
