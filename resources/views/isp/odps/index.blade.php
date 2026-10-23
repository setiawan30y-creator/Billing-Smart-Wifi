@extends('layouts.app')
@section('title', 'Titik ODP & Peta')
@section('content')
@include('partials.leaflet')
<div class="page-head">
  <div><h1>Titik ODP & Peta Jaringan</h1><p>Lokasi ODP, okupansi port, dan pelanggan yang terhubung.</p></div>
  <div class="actions">
    <a href="{{ route('app.odcs.index') }}" class="btn"><x-icon name="layers"/> ODC</a>
    @can('network.odp.manage')<a href="{{ route('app.odps.create') }}" class="btn btn-primary"><x-icon name="plus"/> Titik ODP</a>@endcan
  </div>
</div>

<div class="grid grid-4" style="margin-bottom:1rem">
  <div class="card stat"><div class="stat-label">Jumlah ODP</div><div class="stat-value">{{ $totals['odp'] }}</div></div>
  <div class="card stat"><div class="stat-label">Total port</div><div class="stat-value">{{ $totals['ports'] }}</div></div>
  <div class="card stat"><div class="stat-label">Port terpakai</div><div class="stat-value">{{ $totals['used'] }}</div></div>
  <div class="card stat"><div class="stat-label">Port kosong</div><div class="stat-value" style="color:var(--success)">{{ max(0, $totals['ports'] - $totals['used']) }}</div></div>
</div>

<div class="card" style="margin-bottom:1rem"><div class="card-body">
  @if (count($mapData['odps']))
    <div id="network-map" class="map"></div>
    <div class="map-legend"><span><i style="background:#12805c"></i>Port tersedia</span><span><i style="background:#d38a10"></i>≥ 75% terpakai</span><span><i style="background:#c22f2f"></i>Penuh</span><span><i style="background:#6b7a90"></i>Perbaikan / nonaktif</span><span><i style="background:#0b6bcb"></i>Pelanggan (garis putus = perkiraan drop kabel)</span></div>
  @else
    <div class="empty">Belum ada ODP dengan koordinat. Tambahkan titik ODP dan tentukan lokasinya di peta.</div>
  @endif
</div></div>

<div class="card">
  <form class="toolbar" method="GET">
    <input class="input grow" name="q" value="{{ request('q') }}" placeholder="Cari kode, nama, atau alamat ODP">
    <select name="odc" class="input"><option value="">Semua ODC</option>@foreach ($odcs as $c)<option value="{{ $c->id }}" @selected(request('odc') == $c->id)>{{ $c->code }}</option>@endforeach</select>
    <select name="status" class="input"><option value="">Semua status</option>@foreach (\App\Models\Odp::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
    <label class="check small"><input type="checkbox" name="full" value="1" @checked(request('full') === '1')> Hanya yang penuh</label>
    <button class="btn"><x-icon name="search"/> Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ODP</th><th>ODC / OLT</th><th>Lokasi</th><th>Splitter</th><th>Port</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($odps as $o)
      @php $lvl = $o->status === 'active' ? $o->usageLevel($o->used_count) : 'muted'; $pct = $o->capacity ? min(100, round($o->used_count * 100 / $o->capacity)) : 0; @endphp
      <tr>
        <td><a href="{{ route('app.odps.show', $o) }}"><strong class="mono">{{ $o->code }}</strong></a><div class="small">{{ $o->name }}</div></td>
        <td class="small">{{ $o->odc?->code ?? '-' }}<div class="faint">{{ $o->olt?->name }}{{ $o->pon_port ? ' · PON '.$o->pon_port : '' }}</div></td>
        <td class="small">{{ \Illuminate\Support\Str::limit($o->address, 40) ?: '-' }}@if ($o->latitude === null)<div><x-badge status="pending" label="belum ada titik"/></div>@endif</td>
        <td>{{ $o->splitter ?? '-' }}</td>
        <td style="min-width:120px"><div class="small"><strong>{{ $o->used_count }}</strong> / {{ $o->capacity }}</div><div class="progress {{ $lvl === 'danger' ? 'over' : ($lvl === 'warning' ? 'warn' : '') }}" style="margin-top:.3rem"><span style="width:{{ $pct }}%"></span></div></td>
        <td><x-badge :status="$o->status === 'active' ? 'active' : ($o->status === 'maintenance' ? 'pending' : 'void')" :label="\App\Models\Odp::STATUSES[$o->status]"/></td>
      </tr>
    @empty <tr><td colspan="6" class="empty">Belum ada titik ODP.</td></tr> @endforelse
    </tbody></table></div>
</div>
@if (count($mapData['odps']))
@push('scripts')<script>document.addEventListener('DOMContentLoaded', function () { AlmaraMap.network(document.getElementById('network-map'), @json($mapData)); });</script>@endpush
@endif
@endsection
