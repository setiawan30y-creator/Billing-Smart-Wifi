@extends('portal.layout')
@section('title', 'Bayar tagihan')
@section('content')
<h1>Bayar tagihan</h1>
<div class="card" style="margin-bottom:1rem"><div class="card-body">
  <div class="small muted">{{ $invoice->number }}{{ $invoice->period ? ' · periode '.$invoice->period : '' }}</div>
  <div style="font-size:1.6rem;font-weight:800">{{ rupiah($invoice->balance()) }}</div>
  <div class="small muted">Jatuh tempo {{ tgl($invoice->due_date) }}</div>
</div></div>
<div class="card" style="margin-bottom:1rem"><div class="card-head"><h3>Cara pembayaran</h3></div><div class="card-body" style="white-space:pre-wrap">{{ $tenant->setting('payment_instructions') ?: 'Silakan hubungi '.$tenant->displayName().' ('.($tenant->profile?->whatsapp ?? '-').') untuk informasi rekening pembayaran.' }}</div></div>
<div class="card"><div class="card-head"><h3>Kirim bukti transfer</h3></div>
  <form method="POST" enctype="multipart/form-data" action="{{ route('portal.invoices.proof', [$tenant->code, $invoice->id]) }}" class="card-body stack">
    @csrf <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
    <x-field name="amount" label="Jumlah yang ditransfer (Rp)" :value="(int) $invoice->balance()" required inputmode="numeric"/>
    <x-field name="paid_at" label="Waktu transfer" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required/>
    <x-field name="reference" label="No. referensi / nama pengirim" placeholder="Opsional"/>
    <x-field name="proof" label="Foto / PDF bukti transfer (maks 4 MB)" type="file" accept="image/*,application/pdf" required/>
    <button class="btn btn-primary btn-block">Kirim bukti pembayaran</button>
    <p class="small muted" style="margin:0">Tagihan dinyatakan lunas setelah pembayaran diverifikasi oleh {{ $tenant->displayName() }}.</p>
  </form></div>
@endsection
