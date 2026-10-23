@extends('layouts.app')
@section('title', $user->exists ? 'Ubah pengguna' : 'Pengguna baru')
@section('content')
<div class="page-head"><h1>{{ $user->exists ? 'Ubah '.$user->name : 'Pengguna baru' }}</h1><a href="{{ route('app.users.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ $user->exists ? route('app.users.update', $user) : route('app.users.store') }}" class="card" style="max-width:760px">
  @csrf @if ($user->exists) @method('PUT') @endif
  <div class="card-body form-grid">
    <x-field name="name" label="Nama" :value="$user->name" required placeholder="Nama lengkap"/>
    <x-field name="email" label="Email login" type="email" :value="$user->email" required placeholder="email@perusahaan.com"/>
    <x-field name="phone" label="Nomor HP" :value="$user->phone" placeholder="08xxxxxxxxxx"/>
    @if ($user->exists)<label class="check" style="align-self:end"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Akun aktif</label>@endif
    <div class="field full"><label class="req">Role</label>
      <div class="grid grid-2">@foreach ($roles as $r)<label class="check"><input type="checkbox" name="roles[]" value="{{ $r->id }}" @checked(in_array($r->id, old('roles', $selected)))> {{ $r->name }}</label>@endforeach</div>
      @error('roles')<div class="error">{{ $message }}</div>@enderror</div>
    @unless ($user->exists)<p class="full small muted" style="margin:0">Setelah disimpan, sistem membuat link aktivasi sekali pakai (berlaku 48 jam) untuk dibagikan ke pengguna. Password tidak pernah dikirim dalam teks biasa.</p>@endunless
  </div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan</button></div>
</form>
@endsection
