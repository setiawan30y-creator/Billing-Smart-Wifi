@extends('portal.layout')
@section('title', 'Profil')
@section('content')
<h1>Profil</h1>
<div class="card" style="margin-bottom:1rem"><div class="card-body"><dl class="kv" style="grid-template-columns:120px 1fr">
  <dt>Nama</dt><dd>{{ $customer->name }}</dd><dt>ID pelanggan</dt><dd class="mono">{{ $customer->customer_code }}</dd>
  <dt>WhatsApp</dt><dd>{{ $customer->phone }}</dd><dt>Alamat</dt><dd>{{ $customer->address }}</dd>
</dl><p class="small muted" style="margin:.75rem 0 0">Untuk mengubah data di atas, kirim tiket bantuan kategori "Lainnya".</p></div></div>
<div class="card"><div class="card-head"><h3>Ganti password</h3></div>
  <form method="POST" action="{{ route('portal.profile.password', $tenant->code) }}" class="card-body stack">@csrf @method('PUT')
    <x-field name="current_password" label="Password saat ini" type="password" required autocomplete="current-password"/>
    <x-field name="password" label="Password baru" type="password" required autocomplete="new-password" hint="Minimal 8 karakter, huruf & angka."/>
    <x-field name="password_confirmation" label="Ulangi password baru" type="password" required autocomplete="new-password"/>
    <button class="btn btn-primary">Simpan</button>
  </form></div>
@endsection
