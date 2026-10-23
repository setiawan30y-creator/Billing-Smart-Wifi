@extends('layouts.app')
@section('title', $target->exists ? 'Ubah target' : 'Target monitoring')
@section('content')
<div class="page-head"><h1>{{ $target->exists ? 'Ubah '.$target->name : 'Target monitoring baru' }}</h1><a href="{{ $target->exists ? route('app.monitoring.show', $target) : route('app.monitoring.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ $target->exists ? route('app.monitoring.update', $target) : route('app.monitoring.store') }}" class="card" style="max-width:860px" x-data="{ m: '{{ old('method', $target->method) }}' }">@csrf @if ($target->exists) @method('PUT') @endif
  <div class="card-body form-grid">
    <div class="field full"><label class="req">Metode pengukuran</label>
      @foreach (\App\Models\MonitoringTarget::METHODS as $k => $l)<label class="check" style="display:flex;margin-bottom:.3rem"><input type="radio" name="method" value="{{ $k }}" x-model="m"> {{ $l }}</label>@endforeach
      <div class="hint" x-show="m === 'icmp'">Memakai perintah <span class="mono">ping</span> di server aplikasi. Di beberapa server ICMP diblokir — gunakan TCP bila hasilnya selalu error.</div>
      <div class="hint" x-show="m === 'routeros'" x-cloak>Kredensial API diambil dari perangkat di registry. Gunakan user RouterOS group <strong>read</strong>; aplikasi tidak pernah mengubah konfigurasi router.</div></div>
    <div class="field"><label :class="m === 'routeros' ? 'req' : ''">Perangkat (registry)</label><select name="network_device_id" class="input"><option value="">— Tidak terkait —</option>@foreach ($devices as $d)<option value="{{ $d->id }}" @selected(old('network_device_id', $target->network_device_id) == $d->id)>{{ $d->name }} ({{ $d->host }})</option>@endforeach</select></div>
    <x-field name="name" label="Nama target" :value="$target->name" required/>
    <x-field name="host" label="Host / IP" :value="$target->host" required placeholder="192.168.88.1"/>
    <div class="field" x-show="m === 'tcp'"><label class="req">Port TCP</label><input name="port" type="number" class="input" value="{{ old('port', $target->port) }}" placeholder="8728 / 80 / 22"></div>
    <div class="field" x-show="m === 'routeros'" x-cloak><label>Interface untuk trafik</label><input name="interface_name" class="input" value="{{ old('interface_name', $target->interface_name) }}" placeholder="mis. ether1 / sfp1"><div class="hint">Throughput diambil sesaat (monitor-traffic once) setiap interval.</div></div>
    <x-field name="interval_minutes" label="Interval (menit)" type="number" :value="$target->interval_minutes" min="1" max="60" required/>
    <x-field name="latency_warn_ms" label="Batas latensi peringatan (ms)" type="number" :value="$target->latency_warn_ms" required/>
    <x-field name="loss_warn_pct" label="Batas packet loss peringatan (%)" type="number" :value="$target->loss_warn_pct" required/>
    <x-field name="down_after_failures" label="Dianggap down setelah gagal berturut-turut" type="number" :value="$target->down_after_failures" min="1" max="10" required/>
    <label class="check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $target->enabled))> Aktif</label>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border);display:flex;justify-content:space-between"><button class="btn btn-primary">Simpan</button></div>
</form>
@if ($target->exists)<form method="POST" action="{{ route('app.monitoring.destroy', $target) }}" style="margin-top:1rem">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Hapus target & sampel</button></form>@endif
@endsection
