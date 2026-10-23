@extends('layouts.app')
@section('title', $item->name)
@section('content')
<div class="page-head"><div><h1>{{ $item->name }}</h1><p class="mono">{{ $item->sku }} · {{ \App\Models\InventoryItem::CATEGORIES[$item->category] }} · total {{ \App\Models\InventoryItem::qty($item->totalStock()) }} {{ $item->unit }}</p></div>
  <div class="actions"><a href="{{ route('app.inventory.index') }}" class="btn">Inventaris</a>@can('inventory.manage')<a href="{{ route('app.inventory.edit', $item) }}" class="btn">Ubah</a>@endcan</div></div>
<div class="grid grid-main">
  <div class="stack">
    <div class="card"><div class="card-head"><h3>Saldo per lokasi</h3></div><div class="table-wrap"><table class="table"><tbody>
      @forelse ($item->balances as $b)<tr><td>{{ $b->location->name }} <span class="small faint">({{ \App\Models\InventoryLocation::TYPES[$b->location->type] }})</span></td><td class="num"><strong>{{ \App\Models\InventoryItem::qty($b->quantity) }}</strong> {{ $item->unit }}</td></tr>
      @empty <tr><td class="empty">Belum ada stok.</td></tr> @endforelse
    </tbody></table></div></div>
    @if ($item->track_serial)
    <div class="card"><div class="card-head"><h3>Serial number</h3></div><div class="table-wrap"><table class="table">
      <thead><tr><th>Serial</th><th>Status</th><th>Lokasi / pelanggan</th><th></th></tr></thead><tbody>
      @forelse ($serials as $s)<tr><td class="mono">{{ $s->serial }}</td><td><x-badge :status="['in_stock' => 'active', 'installed' => 'done', 'faulty' => 'failed', 'returned' => 'muted'][$s->status]" :label="\App\Models\InventorySerialNumber::STATUSES[$s->status]"/></td>
        <td class="small">{{ $s->location?->name ?? '' }}@if ($s->service?->customer)<a href="{{ route('app.customers.show', $s->service->customer_id) }}">{{ $s->service->customer->name }}</a>@endif</td>
        <td class="right">@can('inventory.manage')@if ($s->status === 'in_stock')<form method="POST" action="{{ route('app.inventory.serial.faulty', $s) }}" style="display:flex;gap:.3rem;justify-content:flex-end">@csrf<input name="reason" class="input" style="padding:.3rem .5rem;max-width:160px" placeholder="Alasan rusak" required><button class="btn btn-sm">Tandai rusak</button></form>@endif @endcan</td></tr>
      @empty <tr><td colspan="4" class="empty">Belum ada serial.</td></tr> @endforelse
    </tbody></table></div></div>
    @endif
    <div class="card"><div class="card-head"><h3>Riwayat mutasi</h3></div><div class="table-wrap"><table class="table">
      <thead><tr><th>Waktu</th><th>Jenis</th><th>Dari → Ke</th><th class="num">Jumlah</th><th>Keterangan</th></tr></thead><tbody>
      @forelse ($movements as $m)<tr><td class="small nowrap">{{ $m->created_at->format('d/m/Y H:i') }}<div class="faint mono">{{ $m->number }}</div></td><td>{{ \App\Models\InventoryMovement::TYPES[$m->type] }}</td>
        <td class="small">{{ $m->from?->code ?? '—' }} → {{ $m->to?->code ?? '—' }}</td><td class="num">{{ \App\Models\InventoryItem::qty($m->quantity) }}</td>
        <td class="small">{{ $m->serial ? 'SN '.$m->serial->serial.' · ' : '' }}{{ $m->reason }}@if ($m->work_order_id) · <a href="{{ route('app.work-orders.show', $m->work_order_id) }}">WO</a>@endif<div class="faint">{{ $m->user?->name }}</div></td></tr>
      @empty <tr><td colspan="5" class="empty">Belum ada mutasi.</td></tr> @endforelse
    </tbody></table></div>{{ $movements->links() }}</div>
  </div>
  @can('inventory.manage')
  <div class="stack" x-data="{ type: 'receive', from: '' }">
    <div class="card"><div class="card-head"><h3>Catat mutasi stok</h3></div>
      <form method="POST" action="{{ route('app.inventory.movement', $item) }}" class="card-body stack">@csrf
        <div class="field"><label>Jenis</label><select name="type" class="input" x-model="type"><option value="receive">Penerimaan (barang masuk)</option><option value="transfer">Perpindahan antar lokasi</option>@unless ($item->track_serial)<option value="adjust">Penyesuaian (stock opname)</option>@endunless</select></div>
        <div class="field"><label x-text="type === 'transfer' ? 'Dari lokasi' : 'Lokasi'"></label><select name="location_id" class="input" x-model="from" required><option value="">Pilih</option>@foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
        <div class="field" x-show="type === 'transfer'"><label>Ke lokasi</label><select name="to_location_id" class="input"><option value="">Pilih</option>@foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
        @if ($item->track_serial)
          <div class="field" x-show="type === 'receive'"><label>Serial number (satu per baris)</label><textarea name="serials" class="input mono" rows="5" placeholder="ZTEG12345678&#10;ZTEG12345679"></textarea></div>
          <div class="field" x-show="type === 'transfer'"><label>Pilih serial</label><div style="max-height:200px;overflow:auto">
            @foreach ($serials->where('status', 'in_stock') as $s)<label class="check small" x-show="String({{ $s->inventory_location_id ?? 0 }}) === from"><input type="checkbox" name="serial_ids[]" value="{{ $s->id }}"> <span class="mono">{{ $s->serial }}</span></label><br>@endforeach
          </div></div>
        @else
          <div class="field"><label x-text="type === 'adjust' ? 'Jumlah fisik sebenarnya' : 'Jumlah'"></label><input name="quantity" type="number" step="0.01" min="0" class="input" required></div>
        @endif
        <div class="field"><label :class="type === 'adjust' ? 'req' : ''">Keterangan / alasan</label><input name="reason" class="input" placeholder="mis. Pembelian dari supplier / hasil stock opname"></div>
        <button class="btn btn-primary">Simpan mutasi</button>
      </form></div>
  </div>
  @endcan
</div>
@endsection
