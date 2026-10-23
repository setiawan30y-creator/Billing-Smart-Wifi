@extends('layouts.app')
@section('title', 'Komisi Kolektor')
@section('content')
<div class="page-head"><div><h1>Komisi Kolektor / Agen</h1><p>Dihitung otomatis hanya dari pembayaran yang sudah terverifikasi.</p></div>
  @if ($manage)<a href="{{ route('app.commissions.assignments') }}" class="btn">Penugasan penagihan</a>@endif</div>

<div class="grid grid-3" style="margin-bottom:1rem">
  @foreach ($manage ? $collectors : $collectors->where('id', auth()->id()) as $c)
    @php $rows = $summary[$c->id] ?? collect(); @endphp
    <div class="card stat"><div class="stat-label">{{ $c->name }} · {{ $assignmentCount[$c->id] ?? 0 }} pelanggan</div>
      <div class="stat-value">{{ rupiah($rows->firstWhere('status', 'pending')?->total ?? 0) }}</div>
      <div class="stat-sub">belum dibayar · sudah dibayar {{ rupiah($rows->firstWhere('status', 'settled')?->total ?? 0) }}</div></div>
  @endforeach
  @if ($collectors->isEmpty())<div class="card card-body small muted">Belum ada pengguna dengan role Petugas Kolektor / Agen.</div>@endif
</div>

<div class="grid grid-main">
  <div class="card">
    <form class="toolbar" method="GET">
      @if ($manage)<select name="user" class="input"><option value="">Semua kolektor</option>@foreach ($collectors as $c)<option value="{{ $c->id }}" @selected($userFilter === $c->id)>{{ $c->name }}</option>@endforeach</select>@endif
      <select name="status" class="input">@foreach (['pending' => 'Belum dibayar', 'settled' => 'Sudah dibayar', 'void' => 'Batal', 'semua' => 'Semua'] as $k => $l)<option value="{{ $k }}" @selected(request('status', 'pending') === $k)>{{ $l }}</option>@endforeach</select>
      <button class="btn">Filter</button>
    </form>
    <form method="POST" action="{{ route('app.commissions.settle') }}">@csrf
    <div class="table-wrap"><table class="table">
      <thead><tr>@if ($manage && $userFilter && request('status', 'pending') === 'pending')<th></th>@endif<th>Pembayaran</th><th>Kolektor</th><th class="num">Dasar</th><th class="num">Komisi</th><th>Status</th></tr></thead><tbody>
      @forelse ($entries as $e)<tr>
        @if ($manage && $userFilter && request('status', 'pending') === 'pending')<td><input type="checkbox" name="entries[]" value="{{ $e->id }}" checked></td>@endif
        <td><a class="mono small" href="{{ route('app.payments.show', $e->payment_id) }}">{{ $e->payment?->number }}</a><div class="small faint">{{ $e->payment?->customer?->name }} · {{ $e->created_at->format('d/m/Y') }}</div></td>
        <td class="small">{{ $e->user?->name }}</td><td class="num">{{ rupiah($e->base_amount) }}</td>
        <td class="num" style="{{ (float) $e->amount < 0 ? 'color:var(--danger)' : '' }}">{{ rupiah($e->amount) }}{{ $e->kind === 'reversal' ? ' (koreksi)' : '' }}</td>
        <td><x-badge :status="['pending' => 'pending', 'settled' => 'done', 'void' => 'void'][$e->status]" :label="['pending' => 'Belum dibayar', 'settled' => 'Dibayar', 'void' => 'Batal'][$e->status]"/></td></tr>
      @empty <tr><td colspan="6" class="empty">Tidak ada komisi.</td></tr> @endforelse
    </tbody></table></div>
    @if ($manage && $userFilter && request('status', 'pending') === 'pending' && $entries->count())
      <div class="card-body form-grid" style="border-top:1px solid var(--border)"><input type="hidden" name="user_id" value="{{ $userFilter }}">
        <x-field name="reference" label="Referensi pembayaran komisi" placeholder="mis. transfer / kas"/><x-field name="notes" label="Catatan"/>
        <div><button class="btn btn-primary">Bayarkan komisi terpilih</button></div></div>
    @elseif ($manage && ! $userFilter)<p class="small muted" style="padding:0 1.25rem 1rem">Pilih satu kolektor untuk membayarkan komisinya.</p>@endif
    </form>
    {{ $entries->links() }}
  </div>
  <div class="stack">
    @if ($manage)
    <div class="card"><div class="card-head"><h3>Aturan komisi</h3></div><div class="card-body">
      @forelse ($rules as $r)<div style="display:flex;justify-content:space-between;gap:.5rem;margin-bottom:.5rem"><div class="small"><strong>{{ $r->name }}</strong> — {{ $r->type === 'percent' ? (float) $r->value.'%' : rupiah($r->value) }} · {{ ['any' => 'semua metode', 'cash' => 'tunai', 'transfer' => 'transfer'][$r->method] }}<div class="faint">{{ $r->user?->name ?? 'Semua kolektor' }} · mulai {{ tgl($r->effective_from) }}</div></div>
        <form method="POST" action="{{ route('app.commissions.rules.toggle', $r) }}">@csrf<button class="btn btn-sm">{{ $r->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></div>
      @empty <p class="small muted">Belum ada aturan.</p> @endforelse
    </div>
      <form method="POST" action="{{ route('app.commissions.rules.store') }}" class="card-body stack" style="border-top:1px solid var(--border)">@csrf
        <x-field name="name" label="Nama aturan" required placeholder="mis. Komisi tunai 5%"/>
        <div class="field"><label>Berlaku untuk</label><select name="user_id" class="input"><option value="">Semua kolektor</option>@foreach ($collectors as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
        <div class="form-grid"><div class="field"><label>Jenis</label><select name="type" class="input"><option value="percent">Persen</option><option value="fixed">Nominal tetap</option></select></div><x-field name="value" label="Nilai" required/></div>
        <div class="form-grid"><div class="field"><label>Metode bayar</label><select name="method" class="input"><option value="any">Semua</option><option value="cash">Tunai</option><option value="transfer">Transfer</option></select></div><x-field name="effective_from" label="Mulai" type="date" :value="now()->toDateString()" required/></div>
        <button class="btn">Tambah aturan</button>
      </form></div>
    @endif
    <div class="card"><div class="card-head"><h3>Pembayaran komisi</h3></div><div class="card-body">
      @forelse ($settlements as $s)<div class="small" style="margin-bottom:.5rem"><strong class="mono">{{ $s->number }}</strong> · {{ $s->user?->name }} · {{ rupiah($s->total) }}<div class="faint">{{ $s->paid_at->format('d/m/Y') }} · {{ $s->entries_count }} entri {{ $s->reference ? '· '.$s->reference : '' }}</div></div>
      @empty <p class="small muted">Belum ada.</p> @endforelse
    </div></div>
  </div>
</div>
@endsection
