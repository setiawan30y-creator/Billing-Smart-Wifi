<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Kuitansi {{ $payment->number }}</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}"><style>body{background:#eef1f5}.doc{max-width:640px}</style></head>
<body>
<div class="no-print" style="text-align:center;padding:1rem"><button class="btn btn-primary" onclick="window.print()">Cetak</button></div>
<div class="doc">
  <div style="display:flex;justify-content:space-between;align-items:flex-start">
    <div><h2 style="margin:0">{{ $tenant->displayName() }}</h2><div class="small">{{ $tenant->profile?->address }} · {{ $tenant->profile?->whatsapp }}</div></div>
    <div style="text-align:right"><h2 style="margin:0">KUITANSI</h2><div class="mono small">{{ $payment->number }}</div></div>
  </div>
  <hr style="border:0;border-top:1px solid #ddd;margin:1rem 0">
  <table>
    <tr><td style="width:170px">Telah diterima dari</td><td><strong>{{ $payment->customer->name }}</strong> ({{ $payment->customer->customer_code }})</td></tr>
    <tr><td>Sejumlah</td><td><strong style="font-size:1.2rem">{{ rupiah($payment->amount) }}</strong></td></tr>
    <tr><td>Untuk pembayaran</td><td>@foreach ($payment->allocations as $a){{ $a->invoice->number }} ({{ rupiah($a->amount) }})@if (! $loop->last), @endif @endforeach @if ($payment->allocations->isEmpty()) Deposit / kelebihan bayar @endif</td></tr>
    <tr><td>Metode</td><td>{{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }} {{ $payment->reference ? '· '.$payment->reference : '' }}</td></tr>
    <tr><td>Tanggal</td><td>{{ $payment->paid_at->format('d/m/Y H:i') }} · diverifikasi {{ $payment->verified_at?->format('d/m/Y H:i') }}</td></tr>
  </table>
  <p class="small" style="color:#888;margin-top:2rem;text-align:center">Kuitansi sah dari sistem ALMARA Billing WiFi · {{ config('almara.signature') }}</p>
</div>
</body></html>
