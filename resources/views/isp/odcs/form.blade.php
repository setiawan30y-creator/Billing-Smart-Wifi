@extends('layouts.app')
@section('title', $odc->exists ? 'Ubah ODC' : 'ODC baru')
@section('content')
<div class="page-head"><h1>{{ $odc->exists ? 'Ubah ODC '.$odc->code : 'ODC baru' }}</h1><a href="{{ route('app.odcs.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ $odc->exists ? route('app.odcs.update', $odc) : route('app.odcs.store') }}" class="card" style="max-width:960px">
  @csrf @if ($odc->exists) @method('PUT') @endif
  <div class="card-body form-grid">
    <x-field name="code" label="Kode ODC" :value="$odc->code" required placeholder="mis. ODC-01" style="text-transform:uppercase"/>
    <x-field name="name" label="Nama / area" :value="$odc->name" required/>
    <div class="field"><label>OLT sumber</label><select name="network_device_id" class="input"><option value="">— Tidak dipilih —</option>@foreach ($olts as $d)<option value="{{ $d->id }}" @selected(old('network_device_id', $odc->network_device_id) == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
    <x-field name="capacity" label="Kapasitas (core/port)" type="number" :value="$odc->capacity" min="1"/>
    <x-field name="address" label="Alamat" type="textarea" :value="$odc->address" full rows="2"/>
    @include('partials.map-picker', ['lat' => $odc->latitude, 'lng' => $odc->longitude, 'others' => []])
    <x-field name="notes" label="Catatan" type="textarea" :value="$odc->notes" full rows="2"/>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $odc->is_active))> Aktif</label>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan</button></div>
</form>
@endsection
