@extends('layouts.app')
@section('title', 'Pengaturan Umum')
@section('content')
@php $offsets = $tenant->setting('reminder_offsets', []); @endphp
<div class="page-head"><div><h1>Pengaturan Umum</h1><p>{{ $tenant->name }} · <span class="mono">{{ $tenant->code }}</span></p></div></div>
<form method="POST" action="{{ route('app.settings.update') }}" enctype="multipart/form-data" class="card" style="max-width:860px">
  @csrf @method('PUT')
  <div class="card-body form-section"><p class="section-title">Branding</p><div class="form-grid">
    <x-field name="brand_name" label="Nama aplikasi / brand" :value="$tenant->brand_name" placeholder="{{ $tenant->name }}"/>
    <div class="field"><label>Tema default tenant</label><select name="theme" class="input">@foreach (['default' => 'Default', 'light' => 'Light', 'dark' => 'Dark'] as $k => $l)<option value="{{ $k }}" @selected($tenant->theme === $k)>{{ $l }}</option>@endforeach</select><div class="hint">Preferensi pribadi pengguna diatur di Profil.</div></div>
    <x-field name="logo" label="Logo (PNG/JPEG/WebP, maks 1 MB)" type="file" accept="image/png,image/jpeg,image/webp" hint="Dipakai di aplikasi, invoice, dan kuitansi."/>
    @if ($tenant->logo_path)<div><img src="{{ asset('storage/'.$tenant->logo_path) }}" alt="" style="max-height:60px"></div>@endif
  </div></div>
  <div class="card-body form-section"><p class="section-title">Billing</p><div class="form-grid">
    <x-field name="invoice_prefix" label="Prefix nomor invoice" :value="$tenant->setting('invoice_prefix', 'INV')" required/>
    <div class="field"><label class="req">Mode tagihan default layanan baru</label><select name="default_billing_mode" class="input">
      @foreach (\App\Models\CustomerService::BILLING_MODES as $k => $l)<option value="{{ $k }}" @selected($tenant->setting('default_billing_mode', 'prepaid') === $k)>{{ $l }}</option>@endforeach
    </select><div class="hint">Prabayar: invoice bulan berjalan (bayar di muka). Pascabayar: invoice pemakaian bulan sebelumnya. Bisa diubah per layanan pelanggan.</div></div>
    <div class="field"><label class="req">Kebijakan saat kuota paket penuh</label><select name="quota_policy" class="input">
      <option value="warn" @selected($tenant->setting('quota_policy') === 'warn')>Tetap izinkan + tampilkan peringatan (biaya tambahan berlaku)</option>
      <option value="block_new" @selected($tenant->setting('quota_policy') === 'block_new')>Tolak penambahan client/router baru</option>
    </select><div class="hint">Layanan pelanggan yang sudah ada tidak pernah diputus otomatis karena kuota.</div></div>
    <div class="field full"><label>Jadwal pengingat WhatsApp</label><div class="actions">
      @foreach ([-7 => 'H-7', -3 => 'H-3', -1 => 'H-1', 0 => 'Hari H', 1 => 'H+1', 3 => 'H+3', 7 => 'H+7'] as $v => $l)
        <label class="check"><input type="checkbox" name="reminder_offsets[]" value="{{ $v }}" @checked(in_array($v, $offsets, true))> {{ $l }}</label>
      @endforeach</div><div class="hint">Pengingat berhenti otomatis setelah pembayaran terverifikasi.</div></div>
    <x-field name="payment_instructions" label="Instruksi pembayaran di portal pelanggan" type="textarea" :value="$tenant->setting('payment_instructions')" full rows="4" placeholder="mis. Transfer ke rekening Bank ... a.n. ... lalu unggah bukti transfer." hint="Ditampilkan di halaman Bayar pada portal pelanggan."/>
    <div class="field full"><label>Link portal pelanggan</label><input class="input mono" readonly value="{{ route('portal.login', $tenant->code) }}" onclick="this.select()"><div class="hint">Bagikan link ini ke pelanggan. Akses tiap pelanggan diaktifkan dari halaman detail pelanggan.</div></div>
  </div></div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan pengaturan</button></div>
</form>
@endsection
