@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-head">
  <div><h1>Dashboard</h1><p>{{ $tenant->displayName() }} · {{ now($tenant->timezone)->translatedFormat('l, d F Y') }}</p></div>
  <div class="actions">
    @can('payments.create')<a href="{{ route('app.payments.create') }}" class="btn"><x-icon name="wallet"/> Catat pembayaran</a>@endcan
    @can('customers.create')<a href="{{ route('app.customers.create') }}" class="btn btn-primary"><x-icon name="plus"/> Pelanggan</a>@endcan
  </div>
</div>
@if ($tenant->status === 'trial' && $subscription?->trial_ends_at)
  <div class="alert alert-info">Masa trial berakhir {{ tgl($subscription->trial_ends_at) }}.</div>
@endif
@foreach (['clients' => 'client aktif', 'routers' => 'MikroTik'] as $k => $l)
  @if ($quota[$k]['level'] === 'over')<div class="alert alert-danger">Pemakaian {{ $l }} ({{ $quota[$k]['used'] }}) melewati kuota paket ({{ $quota[$k]['quota'] }}). Biaya tambahan berlaku; layanan pelanggan tidak diputus otomatis.</div>
  @elseif ($quota[$k]['level'] === 'warn')<div class="alert alert-warning">Pemakaian {{ $l }} sudah {{ $quota[$k]['percent'] }}% dari kuota paket.</div>@endif
@endforeach

<div class="grid grid-4" style="margin-bottom:1rem">
  <div class="card stat"><div class="stat-label">Pelanggan</div><div class="stat-value">{{ number_format($customers, 0, ',', '.') }}</div><div class="stat-sub">{{ $quota['clients']['used'] }} client aktif · {{ $quota['services'] }} layanan</div></div>
  <div class="card stat"><div class="stat-label">Piutang berjalan</div><div class="stat-value">{{ rupiah($receivable) }}</div><div class="stat-sub">invoice belum lunas</div></div>
  <div class="card stat"><div class="stat-label">Overdue</div><div class="stat-value" style="color:var(--danger)">{{ rupiah($overdueAmount) }}</div><div class="stat-sub">{{ $overdueCount }} invoice lewat jatuh tempo</div></div>
  <div class="card stat"><div class="stat-label">Terkumpul bulan ini</div><div class="stat-value" style="color:var(--success)">{{ rupiah($collectedMonth) }}</div><div class="stat-sub">{{ $pendingPayments }} pembayaran menunggu verifikasi</div></div>
</div>

<div class="grid grid-2" style="margin-bottom:1rem">
  <div class="card"><div class="card-head"><h3>Invoice terbaru</h3><a href="{{ route('app.invoices.index') }}" class="btn btn-sm">Semua</a></div>
    <div class="table-wrap"><table class="table"><tbody>
      @forelse ($recentInvoices as $i)
        <tr><td><a href="{{ route('app.invoices.show', $i) }}" class="mono small">{{ $i->number }}</a><div class="small">{{ $i->customer?->name }}</div></td><td class="num">{{ rupiah($i->total) }}</td><td class="right"><x-badge :status="$i->status" :label="\App\Models\Invoice::STATUSES[$i->status]"/></td></tr>
      @empty <tr><td class="empty">Belum ada invoice.</td></tr> @endforelse
    </tbody></table></div></div>
  <div class="card"><div class="card-head"><h3>Pembayaran terbaru</h3><a href="{{ route('app.payments.index') }}" class="btn btn-sm">Semua</a></div>
    <div class="table-wrap"><table class="table"><tbody>
      @forelse ($recentPayments as $p)
        <tr><td><a href="{{ route('app.payments.show', $p) }}" class="mono small">{{ $p->number }}</a><div class="small">{{ $p->customer?->name }}</div></td><td class="num">{{ rupiah($p->amount) }}</td><td class="right"><x-badge :status="$p->status" :label="\App\Models\Payment::STATUSES[$p->status]"/></td></tr>
      @empty <tr><td class="empty">Belum ada pembayaran.</td></tr> @endforelse
    </tbody></table></div></div>
</div>

<div class="card"><div class="card-head"><h3>Paket & kuota platform</h3></div><div class="card-body grid grid-3">
  <div><div class="small muted">Paket ALMARA</div><strong>{{ $subscription?->plan?->name ?? '-' }}</strong> <x-badge :status="$tenant->status" :label="$tenant->statusLabel()"/><div class="small faint">s/d {{ tgl($subscription?->ends_at) }}</div></div>
  @foreach (['clients' => 'Client aktif', 'routers' => 'MikroTik'] as $k => $l)
    <div><div class="small muted">{{ $l }}: <strong>{{ $quota[$k]['used'] }}</strong> / {{ $quota[$k]['quota'] ?? '∞' }}</div>
      @if ($quota[$k]['quota'])<div class="progress {{ $quota[$k]['level'] }}" style="margin-top:.4rem"><span style="width:{{ min(100, $quota[$k]['percent']) }}%"></span></div>@endif</div>
  @endforeach
</div></div>
@endsection
