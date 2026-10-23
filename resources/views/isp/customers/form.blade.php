@extends('layouts.app')
@section('title', $customer->exists ? 'Ubah pelanggan' : 'Pelanggan baru')
@section('content')
<div class="page-head"><h1>{{ $customer->exists ? 'Ubah '.$customer->name : 'Pelanggan baru' }}</h1><a href="{{ $customer->exists ? route('app.customers.show', $customer) : route('app.customers.index') }}" class="btn">Kembali</a></div>
<form method="POST" enctype="multipart/form-data" action="{{ $customer->exists ? route('app.customers.update', $customer) : route('app.customers.store') }}" class="card" x-data="{ own: '{{ old('ownership', $customer->ownership) }}' }">
  @csrf @if ($customer->exists) @method('PUT') @endif
  <div class="card-body form-section"><p class="section-title">Identitas & kontak</p><div class="form-grid">
    <x-field name="name" label="Nama" :value="$customer->name" required placeholder="Nama lengkap"/>
    <x-field name="customer_code" label="ID pelanggan" :value="$customer->customer_code" placeholder="Otomatis bila dikosongkan"/>
    <x-field name="phone" label="Nomor WhatsApp" :value="$customer->phone" required placeholder="08xxxxxxxxxx" inputmode="tel"/>
    <x-field name="email" label="Email" type="email" :value="$customer->email" placeholder="Opsional"/>
    <div class="field"><label class="req">Status</label><select name="status" class="input">@foreach (\App\Models\Customer::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $customer->status) === $k)>{{ $l }}</option>@endforeach</select></div>
  </div></div>

  <div class="card-body form-section"><p class="section-title">Alamat pemasangan & lokasi</p><div class="form-grid">
    <x-field name="address" label="Alamat pemasangan" type="textarea" :value="$customer->address" required full rows="2" placeholder="Alamat lengkap"/>
    <x-field name="landmark" label="Patokan rumah" :value="$customer->landmark" placeholder="mis. dekat masjid / warna pagar"/>
    <x-field name="maps_url" label="Link Google Maps" type="url" :value="$customer->maps_url" placeholder="https://maps.app.goo.gl/..."/>
    @include('partials.map-picker', ['lat' => $customer->latitude, 'lng' => $customer->longitude, 'others' => $odpPoints])
    <div class="field"><label class="req">Status rumah</label><select name="ownership" class="input" x-model="own"><option value="own">Milik pribadi</option><option value="rent">Kontrak / sewa</option></select></div>
    <div x-show="own === 'rent'" x-cloak><x-field name="owner_name" label="Nama pemilik rumah" :value="$customer->owner_name" placeholder="Nama pemilik"/></div>
    <x-field name="house_photo" label="Foto rumah / titik instalasi" type="file" accept="image/*" hint="Disimpan privat (bukan URL publik). Maks 4 MB."/>
  </div></div>

  <div class="card-body form-section"><p class="section-title">Data identitas (opsional, sesuai kebijakan ISP)</p><div class="form-grid">
    <x-field name="nik" label="NIK (16 digit)" inputmode="numeric" maxlength="16" placeholder="{{ $customer->nik_last4 ? 'Tersimpan: '.$customer->maskedNik().' — isi untuk mengganti' : '16 digit' }}" hint="Disimpan terenkripsi, ditampilkan ter-masking."/>
    <x-field name="ktp" label="Foto KTP (sisi depan saja)" type="file" accept="image/*" hint="{{ $customer->ktp_path ? 'Sudah ada berkas. Unggah untuk mengganti. ' : '' }}Disimpan privat, akses diaudit. Maks 2 MB."/>
  </div></div>

  @unless ($customer->exists)
  <div class="card-body form-section"><p class="section-title">Layanan awal (opsional)</p><div class="form-grid">
    <div class="field"><label>Paket layanan</label><select name="service_package_id" class="input"><option value="">— Tanpa layanan dulu —</option>@foreach ($packages as $p)<option value="{{ $p->id }}" @selected(old('service_package_id') == $p->id)>{{ $p->name }} · {{ rupiah($p->price) }}</option>@endforeach</select></div>
    <x-field name="billing_day" label="Tanggal tagih (1–28)" type="number" value="1" min="1" max="28"/>
  </div></div>
  @endunless

  <div class="card-body form-section"><x-field name="notes" label="Catatan" type="textarea" :value="$customer->notes" rows="2"/></div>
  <div class="card-body" style="border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:.5rem"><button class="btn btn-primary">Simpan</button></div>
</form>
@endsection
