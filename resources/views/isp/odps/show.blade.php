@extends('layouts.app')
@section('title', 'ODP '.$odp->code)
@section('content')
@include('partials.leaflet')
@php $used = $odp->ports->where('status', 'used')->count(); $broken = $odp->ports->where('status', 'broken')->count(); @endphp
<div class="page-head">
  <div><h1><span class="mono">{{ $odp->code }}</span> <x-badge :status="$odp->status === 'active' ? 'active' : ($odp->status === 'maintenance' ? 'pending' : 'void')" :label="\App\Models\Odp::STATUSES[$odp->status]"/></h1>
    <p>{{ $odp->name }} · {{ $used }}/{{ $odp->capacity }} port terpakai{{ $broken ? ' · '.$broken.' rusak' : '' }}</p></div>
  <div class="actions">
    <a href="{{ route('app.odps.index') }}" class="btn">Semua ODP</a>
    @if ($odp->latitude)<a class="btn" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination={{ $odp->latitude }},{{ $odp->longitude }}"><x-icon name="pin"/> Navigasi</a>@endif
    @can('network.odp.manage')<a href="{{ route('app.odps.edit', $odp) }}" class="btn btn-primary">Ubah</a>@endcan
  </div>
</div>
<div class="grid grid-main">
  <div class="stack">
    <div class="card"><div class="card-head"><h3>Port ODP</h3><span class="small muted">Splitter {{ $odp->splitter ?? '-' }}</span></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th style="width:70px">Port</th><th>Pelanggan / layanan</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($odp->ports as $port)
          <tr>
            <td><strong class="mono">{{ $port->port_number }}</strong></td>
            <td>
              @if ($port->service)
                <a href="{{ route('app.customers.show', $port->service->customer_id) }}"><strong>{{ $port->service->customer?->name }}</strong></a>
                <div class="small faint">{{ $port->service->customer?->customer_code }} · {{ $port->service->package?->name }} · <x-badge :status="$port->service->status" :label="\App\Models\CustomerService::STATUSES[$port->service->status]"/></div>
              @elseif ($port->status === 'broken')
                <span class="small muted">{{ $port->notes ?: 'Port rusak' }}</span>
              @else
                @can('network.odp.manage')
                  @if ($unassigned->count())
                  <form method="POST" action="{{ route('app.odps.ports.assign', $port) }}" style="display:flex;gap:.35rem">@csrf
                    <select name="customer_service_id" class="input" style="padding:.35rem .5rem" required>
                      <option value="">Pilih pelanggan…</option>
                      @foreach ($unassigned as $s)<option value="{{ $s->id }}">{{ $s->customer->name }} ({{ $s->customer->customer_code }}) · {{ $s->package->name }}</option>@endforeach
                    </select><button class="btn btn-sm">Pasang</button></form>
                  @else <span class="small faint">Kosong — semua layanan aktif sudah punya port</span> @endif
                @else <span class="small faint">Kosong</span> @endcan
              @endif
            </td>
            <td><x-badge :status="$port->status === 'used' ? 'active' : ($port->status === 'broken' ? 'failed' : 'muted')" :label="\App\Models\OdpPort::STATUSES[$port->status]"/></td>
            <td class="right nowrap">
              @can('network.odp.manage')
                @if ($port->customer_service_id)
                  <form method="POST" action="{{ route('app.odps.ports.release', $port) }}" style="display:inline">@csrf<button class="btn btn-sm btn-ghost">Lepas</button></form>
                @elseif ($port->status === 'broken')
                  <form method="POST" action="{{ route('app.odps.ports.status', $port) }}" style="display:inline">@csrf<input type="hidden" name="broken" value="0"><button class="btn btn-sm btn-ghost">Tandai normal</button></form>
                @else
                  <form method="POST" action="{{ route('app.odps.ports.status', $port) }}" style="display:inline">@csrf<input type="hidden" name="broken" value="1"><button class="btn btn-sm btn-ghost">Tandai rusak</button></form>
                @endif
              @endcan
            </td>
          </tr>
        @endforeach
        </tbody></table></div>
    </div>
  </div>
  <div class="stack">
    <div class="card"><div class="card-body">
      @if ($odp->latitude)<div id="odp-map" class="map map-sm" style="margin-bottom:1rem"></div>@else<div class="alert alert-warning">Titik ODP belum ditentukan di peta.</div>@endif
      <dl class="kv" style="grid-template-columns:100px 1fr">
        <dt>ODC</dt><dd>{{ $odp->odc ? $odp->odc->code.' · '.$odp->odc->name : '-' }}</dd>
        <dt>OLT</dt><dd>{{ $odp->olt?->name ?? '-' }}{{ $odp->pon_port ? ' · PON '.$odp->pon_port : '' }}</dd>
        <dt>Alamat</dt><dd>{{ $odp->address ?: '-' }}</dd>
        <dt>Patokan</dt><dd>{{ $odp->landmark ?: '-' }}</dd>
        <dt>Koordinat</dt><dd class="mono small">{{ $odp->latitude ? $odp->latitude.', '.$odp->longitude : '-' }}</dd>
        @if ($odp->notes)<dt>Catatan</dt><dd>{{ $odp->notes }}</dd>@endif
      </dl>
    </div></div>
  </div>
</div>
@if ($odp->latitude)
@push('scripts')<script>document.addEventListener('DOMContentLoaded', function () { AlmaraMap.network(document.getElementById('odp-map'), @json($mapData)); });</script>@endpush
@endif
@endsection
