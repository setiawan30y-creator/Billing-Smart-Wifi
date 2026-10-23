@extends('layouts.app')
@section('title', 'ODC')
@section('content')
<div class="page-head"><div><h1>ODC (Optical Distribution Cabinet)</h1><p>Kabinet distribusi antara OLT dan titik ODP.</p></div>
  <div class="actions"><a href="{{ route('app.odps.index') }}" class="btn"><x-icon name="map"/> Titik ODP</a>@can('network.odp.manage')<a href="{{ route('app.odcs.create') }}" class="btn btn-primary"><x-icon name="plus"/> ODC</a>@endcan</div></div>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>ODC</th><th>OLT</th><th>Alamat</th><th class="num">Kapasitas</th><th class="num">Jumlah ODP</th><th>Status</th><th></th></tr></thead>
  <tbody>
  @forelse ($odcs as $c)
    <tr><td><strong class="mono">{{ $c->code }}</strong><div class="small">{{ $c->name }}</div></td><td class="small">{{ $c->olt?->name ?? '-' }}</td>
      <td class="small">{{ \Illuminate\Support\Str::limit($c->address, 50) ?: '-' }}</td><td class="num">{{ $c->capacity ?? '-' }}</td>
      <td class="num"><a href="{{ route('app.odps.index', ['odc' => $c->id]) }}">{{ $c->odps_count }}</a></td>
      <td>@if ($c->is_active)<x-badge status="active" label="Aktif"/>@else<x-badge status="void" label="Nonaktif"/>@endif</td>
      <td class="right">@can('network.odp.manage')<a class="btn btn-sm" href="{{ route('app.odcs.edit', $c) }}">Ubah</a>@endcan</td></tr>
  @empty <tr><td colspan="7" class="empty">Belum ada ODC. ODC opsional — ODP tetap bisa dibuat tanpa ODC.</td></tr> @endforelse
  </tbody></table></div></div>
@endsection
