@extends('layouts.app')
@section('title', $invoice->number)
@section('content')
<div class="page-head">
  <div><h1 class="mono">{{ $invoice->number }} <x-badge :status="$invoice->isOverdue() ? 'failed' : $invoice->status" :label="$invoice->isOverdue() ? 'Overdue' : \App\Models\Invoice::STATUSES[$invoice->status]"/></h1>
    <p><a href="{{ route('app.customers.show', $invoice->customer) }}">{{ $invoice->customer->name }}</a> · {{ $invoice->period ? 'periode '.$invoice->period : 'invoice manual' }}</p></div>
  <div class="actions" x-data="{ v: false }">
    <a href="{{ route('app.invoices.print', $invoice) }}" target="_blank" class="btn"><x-icon name="printer"/> Cetak</a>
    @if (in_array($invoice->status, ['unpaid', 'partial']))
      @can('payments.create')<a href="{{ route('app.payments.create', ['invoice' => $invoice->id]) }}" class="btn btn-primary">Catat pembayaran</a>@endcan
      @can('invoices.void')<button class="btn btn-danger" @click="v = true">Void</button>@endcan
    @endif
    <template x-if="v"><div class="modal-backdrop" @click.self="v = false"><div class="modal"><form method="POST" action="{{ route('app.invoices.void', $invoice) }}" class="card-body stack">@csrf
      <h3>Batalkan invoice?</h3><p class="small muted">Invoice tidak dihapus; statusnya menjadi void dan tercatat di audit. Invoice yang sudah dibayar harus di-reversal dulu.</p>
      <div class="field"><label class="req">Alasan</label><input name="reason" class="input" required maxlength="250"></div>
      <div class="actions" style="justify-content:flex-end"><button type="button" class="btn" @click="v = false">Batal</button><button class="btn btn-danger">Void invoice</button></div>
    </form></div></div></template>
  </div>
</div>
@if ($invoice->status === 'void')<div class="alert alert-danger">Invoice dibatalkan: {{ $invoice->void_reason }}</div>@endif
<div class="grid grid-main">
  <div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Jumlah</th></tr></thead>
    <tbody>@foreach ($invoice->items as $it)<tr><td>{{ $it->description }}</td><td class="num">{{ rtrim(rtrim($it->quantity, '0'), '.') }}</td><td class="num">{{ rupiah($it->unit_price) }}</td><td class="num">{{ rupiah($it->amount) }}</td></tr>@endforeach</tbody>
    <tfoot>
      <tr><td colspan="3" class="num">Subtotal</td><td class="num">{{ rupiah($invoice->subtotal) }}</td></tr>
      @if ((float) $invoice->discount > 0)<tr><td colspan="3" class="num">Diskon</td><td class="num">−{{ rupiah($invoice->discount) }}</td></tr>@endif
      <tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong>{{ rupiah($invoice->total) }}</strong></td></tr>
      <tr><td colspan="3" class="num">Dibayar (terverifikasi)</td><td class="num">{{ rupiah($invoice->amount_paid) }}</td></tr>
      <tr><td colspan="3" class="num"><strong>Sisa</strong></td><td class="num"><strong>{{ rupiah($invoice->balance()) }}</strong></td></tr>
    </tfoot></table></div></div>
  <div class="stack">
    <div class="card"><div class="card-body"><dl class="kv" style="grid-template-columns:110px 1fr">
      <dt>Terbit</dt><dd>{{ tgl($invoice->issue_date) }}</dd><dt>Jatuh tempo</dt><dd>{{ tgl($invoice->due_date) }}</dd>
      @if ($invoice->service)<dt>Layanan</dt><dd>{{ $invoice->service->package?->name }}</dd>@endif
      @if ($invoice->notes)<dt>Catatan</dt><dd>{{ $invoice->notes }}</dd>@endif
    </dl></div></div>
    <div class="card"><div class="card-head"><h3>Alokasi pembayaran</h3></div><div class="card-body">
      @forelse ($invoice->allocations as $a)
        <div style="display:flex;justify-content:space-between;margin-bottom:.4rem"><a class="mono small" href="{{ route('app.payments.show', $a->payment) }}">{{ $a->payment->number }}</a><span>{{ rupiah($a->amount) }} <x-badge :status="$a->payment->status" :label="\App\Models\Payment::STATUSES[$a->payment->status]"/></span></div>
      @empty <p class="small muted">Belum ada pembayaran teralokasi.</p> @endforelse
    </div></div>
  </div>
</div>
@endsection
