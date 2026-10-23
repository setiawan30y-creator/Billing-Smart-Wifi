@extends('layouts.app')
@section('title', 'Pembayaran')
@section('content')
<div class="page-head"><div><h1>Pembayaran</h1><p>Pembayaran baru dianggap sah setelah diverifikasi.</p></div>
  @can('payments.create')<a href="{{ route('app.payments.create') }}" class="btn btn-primary"><x-icon name="plus"/> Catat pembayaran</a>@endcan</div>
<div class="card">
  <form class="toolbar" method="GET">
    <input class="input grow" name="q" value="{{ request('q') }}" placeholder="Cari nomor, referensi, atau pelanggan">
    <select name="status" class="input"><option value="">Semua status</option>@foreach (\App\Models\Payment::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
    <select name="method" class="input"><option value="">Semua metode</option>@foreach (\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach</select>
    <button class="btn"><x-icon name="search"/> Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Nomor</th><th>Pelanggan</th><th>Tanggal</th><th>Metode</th><th class="num">Jumlah</th><th>Dicatat oleh</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($payments as $p)
      <tr><td><a class="mono small" href="{{ route('app.payments.show', $p) }}">{{ $p->number }}</a></td><td>{{ $p->customer?->name }}</td>
        <td class="small nowrap">{{ $p->paid_at->format('d/m/Y H:i') }}</td><td class="small">{{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}@if ($p->reference)<div class="faint mono">{{ $p->reference }}</div>@endif</td>
        <td class="num">{{ rupiah($p->amount) }}</td><td class="small">{{ $p->receiver?->name ?? 'Sistem' }}</td>
        <td><x-badge :status="$p->status" :label="\App\Models\Payment::STATUSES[$p->status]"/></td></tr>
    @empty <tr><td colspan="7" class="empty">Belum ada pembayaran.</td></tr> @endforelse
    </tbody></table></div>
  {{ $payments->links() }}
</div>
@endsection
