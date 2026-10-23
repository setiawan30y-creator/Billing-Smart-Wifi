@extends('layouts.app')
@section('title', 'Riwayat mutasi')
@section('content')
<div class="page-head"><div><h1>Riwayat mutasi stok</h1><p>Catatan permanen; koreksi dilakukan dengan penyesuaian.</p></div><a href="{{ route('app.inventory.index') }}" class="btn">Inventaris</a></div>
<div class="card">
  <form class="toolbar" method="GET"><select name="type" class="input" onchange="this.form.submit()"><option value="">Semua jenis</option>@foreach (\App\Models\InventoryMovement::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select></form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Waktu</th><th>Barang</th><th>Jenis</th><th>Dari → Ke</th><th class="num">Jumlah</th><th>Keterangan</th><th>Oleh</th></tr></thead><tbody>
    @forelse ($movements as $m)<tr><td class="small nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td><td><a href="{{ route('app.inventory.show', $m->inventory_item_id) }}">{{ $m->item->name }}</a>@if ($m->serial)<div class="small mono">SN {{ $m->serial->serial }}</div>@endif</td>
      <td>{{ \App\Models\InventoryMovement::TYPES[$m->type] }}</td><td class="small">{{ $m->from?->code ?? '—' }} → {{ $m->to?->code ?? '—' }}</td><td class="num">{{ \App\Models\InventoryItem::qty($m->quantity) }}</td>
      <td class="small">{{ $m->reason }}@if ($m->work_order_id) · <a href="{{ route('app.work-orders.show', $m->work_order_id) }}">WO</a>@endif</td><td class="small">{{ $m->user?->name }}</td></tr>
    @empty <tr><td colspan="7" class="empty">Belum ada mutasi.</td></tr> @endforelse
  </tbody></table></div>
  {{ $movements->links() }}
</div>
@endsection
