@extends('layouts.app')
@section('title', 'Catat pembayaran')
@section('content')
<div class="page-head"><div><h1>Catat pembayaran</h1><p>Pembayaran manual dicatat dengan identitas petugas, referensi, dan bukti.</p></div><a href="{{ route('app.payments.index') }}" class="btn">Kembali</a></div>
@if (! $customerId)
  <form method="GET" class="card" style="max-width:640px"><div class="card-body stack">
    <div class="field"><label class="req">Pilih pelanggan</label><select name="customer" class="input" required onchange="this.form.submit()"><option value="">— pilih —</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->customer_code }})</option>@endforeach</select></div>
    <button class="btn">Lanjut</button>
  </div></form>
@else
  @php $cust = $customers->firstWhere('id', $customerId); @endphp
  <form method="POST" enctype="multipart/form-data" action="{{ route('app.payments.store') }}" class="card" style="max-width:860px" x-data="{ method: '{{ old('method', 'cash') }}' }">
    @csrf
    <input type="hidden" name="customer_id" value="{{ $customerId }}">
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
    <div class="card-body"><strong>{{ $cust?->name }}</strong> <span class="mono small faint">{{ $cust?->customer_code }}</span> · <a href="{{ route('app.payments.create') }}" class="small">ganti pelanggan</a></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th style="width:40px"></th><th>Invoice terbuka</th><th>Jatuh tempo</th><th class="num">Sisa</th></tr></thead>
      <tbody>
      @forelse ($openInvoices as $i)
        <tr><td><input type="checkbox" name="invoice_ids[]" value="{{ $i->id }}" @checked(in_array($i->id, old('invoice_ids', $invoice ? [$invoice->id] : [])))></td>
          <td class="mono small">{{ $i->number }}</td><td class="small">{{ tgl($i->due_date) }}</td><td class="num">{{ rupiah($i->balance()) }}</td></tr>
      @empty <tr><td colspan="4" class="empty">Tidak ada invoice terbuka — pembayaran akan tercatat sebagai kelebihan bayar (deposit).</td></tr> @endforelse
      </tbody></table></div>
    <div class="card-body form-grid">
      <p class="full small muted" style="margin:0">Invoice yang dicentang dialokasikan lebih dulu; sisanya ke invoice terbuka tertua. Kelebihan dicatat sebagai saldo belum teralokasi.</p>
      <x-field name="amount" label="Jumlah diterima (Rp)" :value="$invoice ? (int) $invoice->balance() : ($openInvoices->count() ? (int) $openInvoices->sum(fn ($i) => (float) $i->balance()) : null)" required inputmode="numeric"/>
      <div class="field"><label class="req">Metode</label><select name="method" class="input" x-model="method"><option value="cash">Tunai</option><option value="transfer">Transfer bank</option><option value="other">Lainnya</option></select></div>
      <div class="field"><label :class="method === 'transfer' ? 'req' : ''">Nomor referensi</label><input name="reference" class="input" value="{{ old('reference') }}" placeholder="No. mutasi / referensi"></div>
      <x-field name="paid_at" label="Waktu bayar" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required/>
      <x-field name="proof" label="Bukti bayar (JPG/PNG/PDF, maks 4 MB)" type="file" accept="image/*,application/pdf"/>
      <x-field name="notes" label="Catatan" type="textarea" rows="2"/>
      @can('payments.verify')<label class="check full"><input type="checkbox" name="verify_now" value="1" @checked(old('verify_now'))> Langsung verifikasi (dana sudah saya terima/cek)</label>@endcan
    </div>
    <div class="card-body" style="border-top:1px solid var(--border);text-align:right"><button class="btn btn-primary">Simpan pembayaran</button></div>
  </form>
@endif
@endsection
