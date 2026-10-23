@extends('layouts.app')
@section('title', $item->exists ? 'Ubah barang' : 'Barang baru')
@section('content')
<div class="page-head"><h1>{{ $item->exists ? 'Ubah '.$item->name : 'Barang baru' }}</h1><a href="{{ $item->exists ? route('app.inventory.show', $item) : route('app.inventory.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ $item->exists ? route('app.inventory.update', $item) : route('app.inventory.store') }}" class="card" style="max-width:760px">@csrf @if ($item->exists) @method('PUT') @endif
  <div class="card-body form-grid">
    <x-field name="sku" label="SKU / kode" :value="$item->sku" required placeholder="mis. ONT-ZTE-F609" style="text-transform:uppercase"/>
    <x-field name="name" label="Nama barang" :value="$item->name" required/>
    <div class="field"><label class="req">Kategori</label><select name="category" class="input">@foreach (\App\Models\InventoryItem::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(old('category', $item->category) === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label class="req">Satuan</label><select name="unit" class="input">@foreach (\App\Models\InventoryItem::UNITS as $k => $l)<option value="{{ $k }}" @selected(old('unit', $item->unit) === $k)>{{ $l }}</option>@endforeach</select></div>
    <x-field name="min_stock" label="Stok minimum (peringatan)" type="number" step="0.01" :value="$item->min_stock !== null ? (float) $item->min_stock : 0"/>
    <div class="field" style="align-self:end;display:grid;gap:.4rem">
      <label class="check"><input type="checkbox" name="track_serial" value="1" @checked(old('track_serial', $item->track_serial))> Lacak per serial number (ONT, router)</label>
      <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))> Aktif</label>
    </div>
    <x-field name="notes" label="Catatan" type="textarea" :value="$item->notes" full rows="2"/>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan</button></div>
</form>
@endsection
