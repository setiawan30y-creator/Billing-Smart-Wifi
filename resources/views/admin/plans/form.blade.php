@extends('layouts.app')
@section('title', $plan->exists ? 'Ubah paket' : 'Paket baru')
@section('content')
<div class="page-head"><h1>{{ $plan->exists ? 'Ubah paket '.$plan->name : 'Paket SaaS baru' }}</h1><a class="btn" href="{{ route('admin.plans.index') }}">Kembali</a></div>
<form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="card" style="max-width:860px">
  @csrf @if ($plan->exists) @method('PUT') @endif
  <div class="card-body form-grid">
    <x-field name="code" label="Kode" :value="$plan->code" required placeholder="mis. starter"/>
    <x-field name="name" label="Nama paket" :value="$plan->name" required/>
    <x-field name="price_monthly" label="Harga per bulan (Rp)" :value="$plan->price_monthly !== null ? (int) $plan->price_monthly : null" inputmode="numeric" hint="Kosongkan untuk paket custom/kontrak."/>
    <x-field name="price_yearly" label="Harga per tahun (Rp)" :value="$plan->price_yearly !== null ? (int) $plan->price_yearly : null" inputmode="numeric" hint="Opsional."/>
    <x-field name="client_quota" label="Kuota client aktif" type="number" :value="$plan->client_quota" min="1"/>
    <x-field name="router_quota" label="Kuota MikroTik" type="number" :value="$plan->router_quota" min="0"/>
    <x-field name="overage_client_price" label="Tarif tambahan per client (Rp)" :value="(int) $plan->overage_client_price"/>
    <x-field name="overage_router_price" label="Tarif tambahan per router (Rp)" :value="(int) $plan->overage_router_price"/>
    <x-field name="description" label="Deskripsi" type="textarea" :value="$plan->description" full rows="2"/>
    <div class="field full"><label>Fitur termasuk</label>
      <div class="grid grid-3">@foreach (\App\Models\SaasPlan::FEATURES as $k => $l)
        <label class="check"><input type="checkbox" name="features[]" value="{{ $k }}" @checked(in_array($k, old('features', $plan->features ?? [])))> {{ $l }}</label>
      @endforeach</div></div>
    <x-field name="sort_order" label="Urutan" type="number" :value="$plan->sort_order ?? 0"/>
    <div class="field" style="display:grid;gap:.5rem;align-content:end">
      <label class="check"><input type="checkbox" name="is_custom" value="1" @checked(old('is_custom', $plan->is_custom))> Paket custom / kontrak</label>
      <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))> Aktif di katalog</label>
      <label class="check"><input type="checkbox" name="is_price_approved" value="1" @checked(old('is_price_approved', $plan->is_price_approved))> Harga sudah disahkan (boleh ditagihkan)</label>
    </div>
    @if ($plan->exists)<x-field name="reason" label="Alasan perubahan (audit)" full/>@endif
  </div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan</button></div>
</form>
@endsection
