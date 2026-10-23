@extends('layouts.app')
@section('title', $device->exists ? 'Ubah perangkat' : 'Perangkat baru')
@section('content')
<div class="page-head"><h1>{{ $device->exists ? 'Ubah '.$device->name : 'Perangkat baru' }}</h1><a href="{{ route('app.devices.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ $device->exists ? route('app.devices.update', $device) : route('app.devices.store') }}" class="card" style="max-width:820px">
  @csrf @if ($device->exists) @method('PUT') @endif
  <div class="card-body form-grid">
    <x-field name="name" label="Nama perangkat" :value="$device->name" required placeholder="mis. Router POP Utama"/>
    <div class="field"><label class="req">Tipe</label><select name="type" class="input">@foreach (\App\Models\NetworkDevice::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $device->type) === $k)>{{ $l }}</option>@endforeach</select></div>
    <x-field name="host" label="Host / IP" :value="$device->host" required placeholder="192.168.88.1"/>
    <x-field name="api_port" label="Port API" type="number" :value="$device->api_port" hint="RouterOS API default 8728 (8729 untuk TLS)."/>
    <x-field name="username" label="Username API" :value="$device->username" hint="Disarankan akun read-only." autocomplete="off"/>
    <x-field name="password" label="Password API" type="password" autocomplete="new-password" hint="{{ $device->exists ? 'Kosongkan bila tidak diubah. ' : '' }}Disimpan terenkripsi, tidak ditampilkan kembali."/>
    <x-field name="location" label="Lokasi / POP" :value="$device->location"/>
    <div></div>
    <x-field name="latitude" label="Latitude" :value="$device->latitude"/>
    <x-field name="longitude" label="Longitude" :value="$device->longitude"/>
    <x-field name="notes" label="Catatan" type="textarea" :value="$device->notes" full rows="2"/>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $device->is_active))> Aktif</label>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border);display:flex;justify-content:space-between">
    <button class="btn btn-primary">Simpan</button>
  </div>
</form>
@if ($device->exists)
<form method="POST" action="{{ route('app.devices.destroy', $device) }}" style="margin-top:1rem">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Arsipkan perangkat</button></form>
@endif
@endsection
