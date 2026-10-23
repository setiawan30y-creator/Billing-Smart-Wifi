@extends('layouts.app')
@section('title', 'Tagihan SaaS')
@section('content')
<div class="page-head"><div><h1>Tagihan SaaS</h1><p>Biaya sewa platform ALMARA kepada ISP (terpisah dari tagihan internet pelanggan).</p></div></div>
<div class="card" style="margin-bottom:1rem"><form method="POST" action="{{ route('admin.saas-invoices.generate') }}" class="toolbar" style="border:0">
  @csrf
  <strong>Buat invoice:</strong>
  <select name="tenant_id" class="input" required><option value="">Pilih ISP</option>@foreach ($tenants as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->code }})</option>@endforeach</select>
  <input type="month" name="period" class="input" value="{{ now()->format('Y-m') }}" required>
  <button class="btn btn-primary">Generate dari snapshot pemakaian</button>
</form></div>
<div class="card">
  <form class="toolbar" method="GET">
    <select name="tenant" class="input"><option value="">Semua ISP</option>@foreach ($tenants as $t)<option value="{{ $t->id }}" @selected(request('tenant') == $t->id)>{{ $t->name }}</option>@endforeach</select>
    <select name="status" class="input"><option value="">Semua status</option>@foreach (['unpaid' => 'Belum bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', 'void' => 'Void'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
    <button class="btn">Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Nomor</th><th>ISP</th><th>Periode</th><th class="num">Total</th><th class="num">Dibayar</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($invoices as $i)
      <tr><td><a href="{{ route('admin.saas-invoices.show', $i) }}" class="mono">{{ $i->number }}</a> @if ($i->is_simulation)<x-badge status="pending" label="SIMULASI"/>@endif</td>
        <td>{{ $i->tenant?->name }}</td><td>{{ $i->period }}</td>
        <td class="num">{{ rupiah($i->total) }}</td><td class="num">{{ rupiah($i->amount_paid) }}</td>
        <td><x-badge :status="$i->status"/></td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada tagihan SaaS.</td></tr> @endforelse
    </tbody></table></div>
  {{ $invoices->links() }}
</div>
@endsection
