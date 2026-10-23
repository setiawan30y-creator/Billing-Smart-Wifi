@extends('layouts.app')
@section('title', 'Profil')
@section('content')
<div class="card" style="max-width:720px">
  <form method="POST" action="{{ route('profile.update') }}">
    @csrf @method('PUT')
    <div class="card-body form-grid">
      <x-field name="name" label="Nama" :value="$user->name" required placeholder="Nama lengkap"/>
      <x-field name="phone" label="Nomor HP" :value="$user->phone" placeholder="08xxxxxxxxxx"/>
      <div class="field"><label>Email</label><input class="input" value="{{ $user->email }}" disabled></div>
      <div class="field"><label>Tema pribadi</label>
        <select name="theme_preference" class="input">
          <option value="">Ikuti tema tenant / perangkat</option>
          <option value="light" @selected($user->theme_preference === 'light')>Light</option>
          <option value="dark" @selected($user->theme_preference === 'dark')>Dark</option>
        </select></div>
      <div class="full"><p class="section-title" style="margin-top:.5rem">Ganti password (opsional)</p></div>
      <x-field name="current_password" label="Password saat ini" type="password" autocomplete="current-password"/>
      <div></div>
      <x-field name="password" label="Password baru" type="password" autocomplete="new-password" hint="Minimal 10 karakter, huruf & angka."/>
      <x-field name="password_confirmation" label="Ulangi password baru" type="password" autocomplete="new-password"/>
    </div>
    <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan</button></div>
  </form>
</div>
@endsection
