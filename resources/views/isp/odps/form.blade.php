@extends('layouts.app')
@section('title', $odp->exists ? 'Ubah ODP' : 'Titik ODP baru')
@section('content')
<div class="page-head"><h1>{{ $odp->exists ? 'Ubah ODP '.$odp->code : 'Titik ODP baru' }}</h1><a href="{{ $odp->exists ? route('app.odps.show', $odp) : route('app.odps.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ $odp->exists ? route('app.odps.update', $odp) : route('app.odps.store') }}" class="card" style="max-width:960px">
  @csrf @if ($odp->exists) @method('PUT') @endif
  <div class="card-body form-section"><p class="section-title">Identitas ODP</p><div class="form-grid">
    <x-field name="code" label="Kode ODP" :value="$odp->code" required placeholder="mis. ODP-A01" style="text-transform:uppercase"/>
    <x-field name="name" label="Nama / area" :value="$odp->name" required placeholder="mis. Blok A Perumahan"/>
    <div class="field"><label>ODC</label><select name="odc_id" class="input"><option value="">— Tanpa ODC —</option>@foreach ($odcs as $c)<option value="{{ $c->id }}" @selected(old('odc_id', $odp->odc_id) == $c->id)>{{ $c->code }} · {{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label>OLT sumber</label><select name="network_device_id" class="input"><option value="">— Tidak dipilih —</option>@foreach ($olts as $d)<option value="{{ $d->id }}" @selected(old('network_device_id', $odp->network_device_id) == $d->id)>{{ $d->name }} ({{ \App\Models\NetworkDevice::TYPES[$d->type] ?? $d->type }})</option>@endforeach</select></div>
    <x-field name="pon_port" label="Port PON OLT" :value="$odp->pon_port" placeholder="mis. 0/1/3"/>
    <div class="field"><label>Splitter</label><select name="splitter" class="input"><option value="">—</option>@foreach (\App\Models\Odp::SPLITTERS as $sp)<option @selected(old('splitter', $odp->splitter) === $sp)>{{ $sp }}</option>@endforeach</select></div>
    <x-field name="capacity" label="Jumlah port pelanggan" type="number" :value="$odp->capacity" required min="1" max="64" hint="Port 1..N dibuat otomatis. Mengurangi kapasitas hanya bisa bila port yang dihapus kosong."/>
    <div class="field"><label class="req">Status</label><select name="status" class="input">@foreach (\App\Models\Odp::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $odp->status) === $k)>{{ $l }}</option>@endforeach</select></div>
  </div></div>
  <div class="card-body form-section"><p class="section-title">Lokasi</p><div class="form-grid">
    <x-field name="address" label="Alamat" type="textarea" :value="$odp->address" rows="2" placeholder="Alamat / jalan tempat tiang ODP"/>
    <x-field name="landmark" label="Patokan" :value="$odp->landmark" placeholder="mis. tiang depan pos ronda"/>
    @include('partials.map-picker', [
      'lat' => $odp->latitude, 'lng' => $odp->longitude,
      'others' => $others->where('id', '!=', $odp->id)->map(fn ($o) => ['lat' => (float) $o->latitude, 'lng' => (float) $o->longitude, 'label' => $o->code.' · '.$o->name])->values(),
    ])
    <x-field name="notes" label="Catatan" type="textarea" :value="$odp->notes" full rows="2"/>
  </div></div>
  <div class="card-body" style="border-top:1px solid var(--border);display:flex;justify-content:flex-end"><button class="btn btn-primary">Simpan</button></div>
</form>
@if ($odp->exists)
  @can('network.odp.manage')
  <form method="POST" action="{{ route('app.odps.destroy', $odp) }}" style="margin-top:1rem">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Arsipkan ODP</button> <span class="small muted">Hanya bisa bila tidak ada pelanggan terpasang.</span></form>
  @endcan
@endif
@endsection
