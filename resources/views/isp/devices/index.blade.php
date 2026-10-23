@extends('layouts.app')
@section('title', 'Router & Perangkat')
@section('content')
<div class="page-head"><div><h1>Router & Perangkat</h1><p>Registry perangkat jaringan tenant.</p></div>
  @can('monitoring.devices.manage')<a href="{{ route('app.devices.create') }}" class="btn btn-primary"><x-icon name="plus"/> Perangkat</a>@endcan</div>
<div class="card" style="margin-bottom:1rem"><div class="card-body">
  <div class="small muted">Kuota MikroTik paket: <strong>{{ $quota['used'] }}</strong> / {{ $quota['quota'] ?? '∞' }} @if ($quota['over'])<span class="badge badge-danger">+{{ $quota['over'] }} di atas kuota</span>@endif</div>
  @if ($quota['quota'])<div class="progress {{ $quota['level'] }}" style="margin-top:.4rem;max-width:420px"><span style="width:{{ min(100, $quota['percent']) }}%"></span></div>@endif
  <p class="small faint" style="margin:.6rem 0 0">Status & metrik perangkat ada di menu <a href="{{ route('app.monitoring.index') }}">Monitoring</a> (read-only, hanya data yang benar-benar terukur).</p>
</div></div>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Nama</th><th>Tipe</th><th>Host</th><th>Lokasi</th><th>Status</th><th></th></tr></thead>
  <tbody>
  @forelse ($devices as $d)
    <tr><td><strong>{{ $d->name }}</strong></td><td>{{ \App\Models\NetworkDevice::TYPES[$d->type] ?? $d->type }}</td>
      <td class="mono small">{{ $d->host }}{{ $d->api_port ? ':'.$d->api_port : '' }}@if ($d->username)<div class="faint">user: {{ $d->username }} · kredensial terenkripsi</div>@endif</td>
      <td class="small">{{ $d->location ?: '-' }}</td>
      <td>@if ($d->is_active)<x-badge status="active" label="Aktif"/>@else<x-badge status="void" label="Nonaktif"/>@endif</td>
      <td class="right nowrap">@can('monitoring.settings.manage')<a class="btn btn-sm btn-ghost" href="{{ route('app.monitoring.create', ['device' => $d->id]) }}">Pantau</a>@endcan @can('monitoring.devices.manage')<a class="btn btn-sm" href="{{ route('app.devices.edit', $d) }}">Ubah</a>@endcan</td></tr>
  @empty <tr><td colspan="6" class="empty">Belum ada perangkat.</td></tr> @endforelse
  </tbody></table></div></div>
@endsection
