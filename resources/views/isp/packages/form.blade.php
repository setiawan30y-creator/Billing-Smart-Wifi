@extends('layouts.app')
@section('title', $package->exists ? 'Ubah paket' : 'Paket baru')
@section('content')
<div class="page-head"><h1>{{ $package->exists ? 'Ubah paket' : 'Paket layanan baru' }}</h1><a href="{{ route('app.packages.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ $package->exists ? route('app.packages.update', $package) : route('app.packages.store') }}" class="card" style="max-width:760px">
  @csrf @if ($package->exists) @method('PUT') @endif
  <div class="card-body form-grid">
    <x-field name="code" label="Kode" :value="$package->code" required placeholder="mis. HOME20"/>
    <x-field name="name" label="Nama paket" :value="$package->name" required placeholder="mis. Home 20 Mbps"/>
    <x-field name="price" label="Harga per bulan (Rp)" :value="$package->price !== null ? (int) $package->price : null" required inputmode="numeric"/>
    <div></div>
    <x-field name="download_mbps" label="Download (Mbps)" type="number" :value="$package->download_mbps" min="1"/>
    <x-field name="upload_mbps" label="Upload (Mbps)" type="number" :value="$package->upload_mbps" min="1"/>
    <x-field name="description" label="Deskripsi" type="textarea" :value="$package->description" full rows="2"/>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active))> Aktif (dapat dipilih untuk layanan baru)</label>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan</button></div>
</form>
@endsection
