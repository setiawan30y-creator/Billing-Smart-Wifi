@extends('layouts.app')
@section('title', 'Inventaris')
@section('content')
<div class="page-head"><div><h1>Inventaris Perangkat & Material</h1><p>Stok per lokasi; setiap perubahan tercatat sebagai mutasi.</p></div>
  <div class="actions"><a href="{{ route('app.inventory.locations') }}" class="btn">Lokasi stok</a><a href="{{ route('app.inventory.movements') }}" class="btn">Riwayat mutasi</a>@can('inventory.manage')<a href="{{ route('app.inventory.create') }}" class="btn btn-primary"><x-icon name="plus"/> Barang</a>@endcan</div></div>
@if ($lowCount)<div class="alert alert-warning">{{ $lowCount }} barang berada di bawah stok minimum. <a href="{{ route('app.inventory.index', ['low' => 1]) }}">Lihat</a></div>@endif
@if ($locations->isEmpty())<div class="alert alert-info">Buat lokasi stok (gudang / kendaraan / stok teknisi) terlebih dahulu di menu <a href="{{ route('app.inventory.locations') }}">Lokasi stok</a>.</div>@endif
<div class="card">
  <form class="toolbar" method="GET">
    <input class="input grow" name="q" value="{{ request('q') }}" placeholder="Cari nama atau SKU">
    <select name="category" class="input"><option value="">Semua kategori</option>@foreach (\App\Models\InventoryItem::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach</select>
    <label class="check small"><input type="checkbox" name="low" value="1" @checked(request('low'))> Di bawah minimum</label>
    <button class="btn"><x-icon name="search"/> Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Barang</th><th>Kategori</th><th class="num">Total stok</th><th class="num">Minimum</th><th>Per lokasi</th></tr></thead>
    <tbody>
    @forelse ($items as $i)
      @php $total = $i->totalStock(); $low = (float) $i->min_stock > 0 && (float) $total <= (float) $i->min_stock; @endphp
      <tr><td><a href="{{ route('app.inventory.show', $i) }}"><strong>{{ $i->name }}</strong></a><div class="small faint mono">{{ $i->sku }}{{ $i->track_serial ? ' · serial' : '' }}</div></td>
        <td class="small">{{ \App\Models\InventoryItem::CATEGORIES[$i->category] }}</td>
        <td class="num"><strong style="{{ $low ? 'color:var(--danger)' : '' }}">{{ \App\Models\InventoryItem::qty($total) }}</strong> {{ $i->unit }}</td>
        <td class="num small">{{ \App\Models\InventoryItem::qty($i->min_stock) }}</td>
        <td class="small">@foreach ($i->balances->where('quantity', '>', 0) as $b){{ $locations->firstWhere('id', $b->inventory_location_id)?->code }}: {{ \App\Models\InventoryItem::qty($b->quantity) }}@if (! $loop->last) · @endif @endforeach</td></tr>
    @empty <tr><td colspan="5" class="empty">Belum ada barang.</td></tr> @endforelse
    </tbody></table></div>
</div>
@endsection
