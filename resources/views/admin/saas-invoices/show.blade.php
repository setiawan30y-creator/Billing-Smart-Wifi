@extends('layouts.app')
@section('title', $invoice->number)
@section('content')
<div class="page-head"><div><h1 class="mono">{{ $invoice->number }} <x-badge :status="$invoice->status"/></h1><p>{{ $invoice->tenant->name }} · periode {{ $invoice->period }}</p></div><a href="{{ route('admin.saas-invoices.index') }}" class="btn">Kembali</a></div>
@if ($invoice->is_simulation)<div class="alert alert-warning"><strong>SIMULASI.</strong> {{ $invoice->notes }}</div>@endif
<div class="grid grid-main">
  <div class="stack">
    <div class="card"><div class="table-wrap"><table class="table">
      <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Jumlah</th></tr></thead>
      <tbody>@foreach ($invoice->items as $it)<tr><td>{{ $it->description }}</td><td class="num">{{ rtrim(rtrim($it->quantity, '0'), '.') }}</td><td class="num">{{ rupiah($it->unit_price) }}</td><td class="num">{{ rupiah($it->amount) }}</td></tr>@endforeach</tbody>
      <tfoot><tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong>{{ rupiah($invoice->total) }}</strong></td></tr>
        <tr><td colspan="3" class="num">Dibayar</td><td class="num">{{ rupiah($invoice->amount_paid) }}</td></tr>
        <tr><td colspan="3" class="num">Sisa</td><td class="num">{{ rupiah($invoice->balance()) }}</td></tr></tfoot>
    </table></div></div>
    <div class="card"><div class="card-head"><h3>Pembayaran</h3></div><div class="table-wrap"><table class="table">
      <thead><tr><th>Tanggal</th><th>Metode / Ref</th><th class="num">Jumlah</th><th>Status</th><th></th></tr></thead><tbody>
      @forelse ($invoice->payments as $p)
        <tr><td class="small">{{ $p->paid_at->format('d/m/Y H:i') }}</td><td class="small">{{ $p->method }} · {{ $p->reference }}<div class="faint">oleh {{ $p->recorder?->name }}</div></td><td class="num">{{ rupiah($p->amount) }}</td><td><x-badge :status="$p->status"/></td>
          <td class="right">@if ($p->status === 'verified')
            <form method="POST" action="{{ route('admin.saas-payments.reverse', $p) }}" style="display:flex;gap:.3rem">@csrf<input name="reason" class="input" style="padding:.3rem .5rem" placeholder="Alasan reversal" required><button class="btn btn-sm">Reversal</button></form>
          @endif</td></tr>
      @empty <tr><td colspan="5" class="empty">Belum ada pembayaran.</td></tr> @endforelse
    </tbody></table></div></div>
  </div>
  <div class="stack">
    <div class="card"><div class="card-body"><dl class="kv">
      <dt>Terbit</dt><dd>{{ tgl($invoice->issue_date) }}</dd><dt>Jatuh tempo</dt><dd>{{ tgl($invoice->due_date) }}</dd>
      @if ($invoice->usage)<dt>Snapshot</dt><dd class="small">{{ $invoice->usage->active_clients }} client aktif · {{ $invoice->usage->active_services }} layanan · {{ $invoice->usage->routers }} router<br><span class="faint">{{ $invoice->usage->measured_at->format('d/m/Y H:i') }}</span></dd>@endif
    </dl></div></div>
    @if (! $invoice->is_simulation && in_array($invoice->status, ['unpaid', 'partial']))
    <div class="card"><div class="card-head"><h3>Catat pembayaran</h3></div>
      <form method="POST" action="{{ route('admin.saas-invoices.pay', $invoice) }}" class="card-body stack">@csrf
        <x-field name="amount" label="Jumlah (Rp)" :value="(int) $invoice->balance()" required/>
        <div class="field"><label class="req">Metode</label><select name="method" class="input"><option value="transfer">Transfer</option><option value="gateway">Payment Gateway</option><option value="cash">Tunai</option><option value="other">Lainnya</option></select></div>
        <x-field name="reference" label="Referensi" required placeholder="No. referensi / mutasi"/>
        <x-field name="paid_at" label="Tanggal bayar" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required/>
        <button class="btn btn-primary">Simpan pembayaran</button>
        <p class="small muted" style="margin:0">Catat hanya setelah dana terverifikasi di rekening / rekonsiliasi resmi.</p>
      </form></div>
    @endif
  </div>
</div>
@endsection
