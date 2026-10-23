@extends('layouts.app')
@section('title', 'Dashboard Platform')
@section('content')
<div class="page-head">
  <div><h1>Dashboard Platform</h1><p>Ringkasan ISP penyewa ALMARA Billing WiFi.</p></div>
  @can('tenants.create')<a href="{{ route('admin.tenants.create') }}" class="btn btn-primary"><x-icon name="plus"/> Tambah ISP</a>@endcan
</div>
<div class="grid grid-4" style="margin-bottom:1rem">
  <div class="card stat"><div class="stat-label">Total ISP</div><div class="stat-value">{{ $totalTenants }}</div><div class="stat-sub">{{ $byStatus['active'] ?? 0 }} aktif · {{ $byStatus['trial'] ?? 0 }} trial</div></div>
  <div class="card stat"><div class="stat-label">Ditangguhkan</div><div class="stat-value">{{ $byStatus['suspended'] ?? 0 }}</div><div class="stat-sub">{{ $byStatus['provisioning_failed'] ?? 0 }} provisioning gagal</div></div>
  <div class="card stat"><div class="stat-label">Pelanggan internet (semua ISP)</div><div class="stat-value">{{ number_format($totalCustomers, 0, ',', '.') }}</div><div class="stat-sub">{{ $totalRouters }} MikroTik terdaftar</div></div>
  <div class="card stat"><div class="stat-label">Piutang SaaS (non-simulasi)</div><div class="stat-value">{{ rupiah($saasReceivable) }}</div></div>
</div>
<div class="grid grid-main">
  <div class="card">
    <div class="card-head"><h2>ISP terbaru</h2><a href="{{ route('admin.tenants.index') }}" class="btn btn-sm">Lihat semua</a></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>ISP</th><th>Paket</th><th>Status</th><th>Dibuat</th></tr></thead>
      <tbody>
      @forelse ($recentTenants as $t)
        <tr><td><a href="{{ route('admin.tenants.show', $t) }}"><strong>{{ $t->name }}</strong></a><div class="small faint mono">{{ $t->code }}</div></td>
          <td>{{ $t->currentSubscription?->plan?->name ?? '-' }}</td>
          <td><x-badge :status="$t->status" :label="$t->statusLabel()"/></td>
          <td class="nowrap small">{{ tgl($t->created_at) }}</td></tr>
      @empty
        <tr><td colspan="4" class="empty">Belum ada ISP terdaftar.</td></tr>
      @endforelse
      </tbody></table></div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Provisioning gagal</h2></div>
    <div class="card-body">
      @forelse ($failedJobs as $j)
        <div style="margin-bottom:.75rem"><a href="{{ route('admin.tenants.show', $j->tenant_id) }}"><strong>{{ $j->tenant?->name }}</strong></a>
          <div class="small muted">{{ \App\Models\TenantProvisioningJob::STEPS[$j->step] ?? $j->step }} — {{ \Illuminate\Support\Str::limit($j->last_error, 90) }}</div></div>
      @empty
        <p class="muted small">Tidak ada pekerjaan provisioning yang gagal.</p>
      @endforelse
    </div>
  </div>
</div>
@endsection
