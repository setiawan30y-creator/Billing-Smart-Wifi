<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $invoice->number }}</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}"><style>body{background:#eef1f5}</style></head>
<body>
<div class="no-print" style="text-align:center;padding:1rem"><button class="btn btn-primary" onclick="window.print()">Cetak / Simpan PDF</button></div>
<div class="doc">
  <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start">
    <div style="display:flex;gap:.8rem;align-items:center">
      @if ($tenant->logo_path)<img src="{{ asset('storage/'.$tenant->logo_path) }}" alt="" style="height:52px">@endif
      <div><h2 style="margin:0">{{ $tenant->displayName() }}</h2><div class="small">{{ $tenant->profile?->address }}<br>{{ $tenant->profile?->whatsapp }} · {{ $tenant->profile?->email }}</div></div>
    </div>
    <div style="text-align:right"><h1 style="margin:0">INVOICE</h1><div class="mono">{{ $invoice->number }}</div>
      @if ($invoice->status === 'paid')<div class="stamp" style="color:#12805c;margin-top:.5rem">LUNAS</div>@elseif ($invoice->status === 'void')<div class="stamp" style="color:#c22f2f;margin-top:.5rem">VOID</div>@endif</div>
  </div>
  <hr style="border:0;border-top:1px solid #ddd;margin:1.2rem 0">
  <div style="display:flex;justify-content:space-between;gap:1rem">
    <div><div class="small" style="color:#666">Ditagihkan kepada</div><strong>{{ $invoice->customer->name }}</strong> ({{ $invoice->customer->customer_code }})<br><span class="small">{{ $invoice->customer->address }}</span></div>
    <div class="small" style="text-align:right">Tanggal: {{ tgl($invoice->issue_date) }}<br>Jatuh tempo: <strong>{{ tgl($invoice->due_date) }}</strong>@if ($invoice->period)<br>Periode: {{ $invoice->period }}@endif</div>
  </div>
  <table style="margin-top:1.2rem"><thead><tr><th>Deskripsi</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Jumlah</th></tr></thead>
    <tbody>@foreach ($invoice->items as $it)<tr><td>{{ $it->description }}</td><td class="num">{{ rtrim(rtrim($it->quantity, '0'), '.') }}</td><td class="num">{{ rupiah($it->unit_price) }}</td><td class="num">{{ rupiah($it->amount) }}</td></tr>@endforeach</tbody>
    <tfoot>
      @if ((float) $invoice->discount > 0)<tr><td colspan="3" class="num">Diskon</td><td class="num">−{{ rupiah($invoice->discount) }}</td></tr>@endif
      <tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong>{{ rupiah($invoice->total) }}</strong></td></tr>
      <tr><td colspan="3" class="num">Dibayar</td><td class="num">{{ rupiah($invoice->amount_paid) }}</td></tr>
      <tr><td colspan="3" class="num"><strong>Sisa tagihan</strong></td><td class="num"><strong>{{ rupiah($invoice->balance()) }}</strong></td></tr>
    </tfoot></table>
  @if ($invoice->notes)<p class="small" style="margin-top:1rem">Catatan: {{ $invoice->notes }}</p>@endif
  <p class="small" style="color:#888;margin-top:2rem;text-align:center">Dokumen dibuat oleh sistem ALMARA Billing WiFi · {{ config('almara.signature') }}</p>
</div>
</body></html>
