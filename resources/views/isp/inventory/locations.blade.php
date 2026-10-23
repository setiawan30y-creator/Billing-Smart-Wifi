@extends('layouts.app')
@section('title', 'Lokasi stok')
@section('content')
<div class="page-head"><div><h1>Lokasi stok</h1><p>Gudang, kendaraan operasional, dan stok yang dibawa teknisi.</p></div><a href="{{ route('app.inventory.index') }}" class="btn">Inventaris</a></div>
<div class="grid grid-main">
  <div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Lokasi</th><th>Jenis</th><th>Pemegang</th><th class="num">Jenis barang</th></tr></thead><tbody>
    @forelse ($locations as $l)<tr><td><strong>{{ $l->name }}</strong><div class="small mono faint">{{ $l->code }}</div></td><td>{{ \App\Models\InventoryLocation::TYPES[$l->type] }}</td><td class="small">{{ $l->holder?->name ?? '-' }}</td><td class="num">{{ $balances[$l->id] ?? 0 }}</td></tr>
    @empty <tr><td colspan="4" class="empty">Belum ada lokasi.</td></tr> @endforelse
  </tbody></table></div></div>
  @can('inventory.manage')
  <div class="card"><div class="card-head"><h3>Lokasi baru</h3></div>
    <form method="POST" action="{{ route('app.inventory.locations.store') }}" class="card-body stack">@csrf
      <x-field name="code" label="Kode" required placeholder="mis. GDG-01"/>
      <x-field name="name" label="Nama" required placeholder="mis. Gudang utama"/>
      <div class="field"><label>Jenis</label><select name="type" class="input">@foreach (\App\Models\InventoryLocation::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label>Pemegang (untuk stok teknisi/kendaraan)</label><select name="user_id" class="input"><option value="">—</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select><div class="hint">Teknisi hanya boleh memakai stok gudang atau stok miliknya sendiri.</div></div>
      <button class="btn btn-primary">Tambah lokasi</button>
    </form></div>
  @endcan
</div>
@endsection
