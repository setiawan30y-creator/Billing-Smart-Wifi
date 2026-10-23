@extends('layouts.app')
@section('title', $wo->number)
@section('content')
@php $closed = in_array($wo->status, ['completed', 'cancelled']); $done = $wo->checklist->where('is_done', true)->count(); @endphp
<div class="page-head">
  <div><h1>{{ $wo->title }} <x-badge :status="$wo->statusBadge()" :label="\App\Models\WorkOrder::STATUSES[$wo->status]"/></h1>
    <p><span class="mono">{{ $wo->number }}</span> · {{ \App\Models\WorkOrder::TYPES[$wo->type] }} · {{ $wo->scheduled_at ? 'jadwal '.$wo->scheduled_at->format('d/m/Y H:i') : 'belum dijadwalkan' }}</p></div>
  <div class="actions">
    <a href="{{ route('app.work-orders.index') }}" class="btn">Daftar</a>
    @if ($wo->customer?->latitude)<a class="btn" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination={{ $wo->customer->latitude }},{{ $wo->customer->longitude }}"><x-icon name="pin"/> Navigasi</a>
    @elseif ($wo->customer?->maps_url)<a class="btn" target="_blank" rel="noopener" href="{{ $wo->customer->maps_url }}"><x-icon name="pin"/> Navigasi</a>@endif
    @can('work_orders.manage')@unless ($closed)<a href="{{ route('app.work-orders.edit', $wo) }}" class="btn">Ubah / jadwalkan</a>@endunless @endcan
  </div>
</div>

@if ($canWork && ! $closed)
<div class="card" style="margin-bottom:1rem"><div class="card-body" x-data="{ done: false }">
  <div class="actions">
    @foreach (\App\Models\WorkOrder::TRANSITIONS[$wo->status] as $to)
      @continue($to === 'completed' || ($to === 'cancelled' && ! auth()->user()->can('work_orders.manage')) || $to === 'open')
      <form method="POST" action="{{ route('app.work-orders.status', $wo) }}">@csrf<input type="hidden" name="status" value="{{ $to }}">
        <button class="btn {{ $to === 'in_progress' ? 'btn-primary' : ($to === 'cancelled' ? 'btn-danger' : '') }}">{{ ['in_progress' => 'Mulai kerjakan', 'on_hold' => 'Tunda', 'scheduled' => 'Kembalikan ke terjadwal', 'cancelled' => 'Batalkan'][$to] ?? \App\Models\WorkOrder::STATUSES[$to] }}</button></form>
    @endforeach
    @if (in_array('completed', \App\Models\WorkOrder::TRANSITIONS[$wo->status]))<button class="btn btn-primary" @click="done = !done"><x-icon name="check"/> Selesaikan</button>@endif
  </div>
  <form method="POST" action="{{ route('app.work-orders.status', $wo) }}" x-show="done" x-cloak class="stack" style="margin-top:1rem" x-ref="f">@csrf
    <input type="hidden" name="status" value="completed"><input type="hidden" name="latitude" x-ref="lat"><input type="hidden" name="longitude" x-ref="lng">
    <x-field name="note" label="Catatan penyelesaian {{ $done < $wo->checklist->count() ? '(wajib — checklist belum lengkap)' : '' }}" type="textarea" rows="3" placeholder="Hasil pekerjaan, redaman, kendala, dsb."/>
    <div class="actions"><button type="button" class="btn btn-sm" @click="navigator.geolocation && navigator.geolocation.getCurrentPosition(p => { $refs.lat.value = p.coords.latitude; $refs.lng.value = p.coords.longitude; $el.textContent = 'Lokasi tersimpan ✓' })"><x-icon name="pin"/> Sertakan lokasi saya</button>
      <button class="btn btn-primary">Simpan sebagai selesai</button></div>
  </form>
</div></div>
@endif
@if ($wo->status === 'completed')<div class="alert alert-success">Selesai {{ $wo->completed_at?->format('d/m/Y H:i') }}. {{ $wo->completion_notes }}</div>@endif

<div class="grid grid-main">
  <div class="stack">
    <div class="card"><div class="card-head"><h3>Checklist</h3><span class="small muted">{{ $done }}/{{ $wo->checklist->count() }}</span></div>
      <div class="card-body">
        @forelse ($wo->checklist as $c)
          <form method="POST" action="{{ route('app.work-orders.checklist', [$wo, $c]) }}" style="margin-bottom:.4rem">@csrf
            <button class="btn btn-ghost" style="padding:.25rem;justify-content:flex-start;width:100%;font-weight:500" @disabled(! $canWork || $closed)>
              <span style="width:20px;height:20px;border-radius:6px;border:2px solid {{ $c->is_done ? 'var(--success)' : 'var(--border)' }};background:{{ $c->is_done ? 'var(--success)' : 'transparent' }};color:#fff;display:inline-grid;place-items:center;font-size:12px">{{ $c->is_done ? '✓' : '' }}</span>
              <span style="{{ $c->is_done ? 'text-decoration:line-through;color:var(--text-3)' : '' }}">{{ $c->label }}</span></button></form>
        @empty <p class="small muted">Tidak ada checklist.</p> @endforelse
        @if ($canWork && ! $closed)<form method="POST" action="{{ route('app.work-orders.checklist.add', $wo) }}" style="display:flex;gap:.4rem;margin-top:.5rem">@csrf<input name="label" class="input" placeholder="Tambah item checklist" required><button class="btn btn-sm">Tambah</button></form>@endif
      </div></div>

    <div class="card"><div class="card-head"><h3>Foto pekerjaan</h3></div><div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.6rem;margin-bottom:.8rem">
        @forelse ($wo->media as $m)
          <a href="{{ route('app.work-orders.media.file', [$wo, $m]) }}" target="_blank" style="display:block"><img src="{{ route('app.work-orders.media.file', [$wo, $m]) }}" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px;border:1px solid var(--border)"><div class="small faint">{{ ['before' => 'Sebelum', 'after' => 'Sesudah', 'other' => 'Lainnya'][$m->kind] }}{{ $m->caption ? ' · '.$m->caption : '' }}</div></a>
        @empty <p class="small muted">Belum ada foto.</p> @endforelse
      </div>
      @if ($canWork && ! $closed)
      <form method="POST" enctype="multipart/form-data" action="{{ route('app.work-orders.media', $wo) }}" class="form-grid">@csrf
        <div class="field"><label>Foto (bisa lebih dari satu)</label><input type="file" name="photos[]" multiple accept="image/*" capture="environment" class="input" required></div>
        <div class="field"><label>Jenis</label><select name="kind" class="input"><option value="before">Sebelum</option><option value="after">Sesudah</option><option value="other">Lainnya</option></select></div>
        <x-field name="caption" label="Keterangan" placeholder="Opsional"/>
        <div style="align-self:end"><button class="btn">Unggah foto</button></div>
      </form>
      @endif
    </div></div>

    <div class="card"><div class="card-head"><h3>Material terpakai</h3></div>
      <div class="table-wrap"><table class="table"><tbody>
        @forelse ($wo->materials as $m)
          <tr><td><strong>{{ $m->item->name }}</strong>@if ($m->serial)<div class="small mono">SN {{ $m->serial->serial }}</div>@endif<div class="small faint">dari {{ $m->location->name }}</div></td>
            <td class="num">{{ \App\Models\InventoryItem::qty($m->quantity) }} {{ $m->item->unit }}</td>
            <td class="right">@if ($m->returned)<x-badge status="muted" label="dikembalikan"/>@elseif ($canWork && ! $closed)
              <form method="POST" action="{{ route('app.work-orders.material.return', [$wo, $m]) }}" style="display:flex;gap:.3rem">@csrf<input name="reason" class="input" style="padding:.3rem .5rem" placeholder="Alasan kembali" required><button class="btn btn-sm">Kembalikan</button></form>@endif</td></tr>
        @empty <tr><td class="empty">Belum ada material dicatat.</td></tr> @endforelse
      </tbody></table></div>
      @if ($canWork && ! $closed)
      <form method="POST" action="{{ route('app.work-orders.material', $wo) }}" class="card-body form-grid" style="border-top:1px solid var(--border)"
        x-data="{ loc: '', item: '', stock: {{ \Illuminate\Support\Js::from($stock->map(fn ($b) => ['loc' => $b->inventory_location_id, 'item' => $b->inventory_item_id, 'name' => $b->item->name.' ('.\App\Models\InventoryItem::qty($b->quantity).' '.$b->item->unit.')', 'serial' => $b->item->track_serial])) }}, serials: {{ \Illuminate\Support\Js::from($serials->map(fn ($s) => ['id' => $s->id, 'loc' => $s->inventory_location_id, 'item' => $s->inventory_item_id, 'sn' => $s->serial])) }},
          get isSerial() { const s = this.stock.find(x => String(x.loc) === this.loc && String(x.item) === this.item); return s && s.serial } }">@csrf
        <div class="field"><label>Ambil dari</label><select name="inventory_location_id" class="input" x-model="loc" required><option value="">Pilih lokasi stok</option>@foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }} ({{ \App\Models\InventoryLocation::TYPES[$l->type] }})</option>@endforeach</select></div>
        <div class="field"><label>Barang</label><select name="inventory_item_id" class="input" x-model="item" required><option value="">Pilih barang</option><template x-for="s in stock.filter(s => String(s.loc) === loc)" :key="s.item"><option :value="s.item" x-text="s.name"></option></template></select></div>
        <div class="field" x-show="!isSerial"><label>Jumlah</label><input name="quantity" class="input" type="number" step="0.01" min="0.01" value="1"></div>
        <div class="field" x-show="isSerial" x-cloak><label>Serial number</label><select name="inventory_serial_number_id" class="input"><option value="">Pilih SN</option><template x-for="s in serials.filter(s => String(s.loc) === loc && String(s.item) === item)" :key="s.id"><option :value="s.id" x-text="s.sn"></option></template></select></div>
        <div style="align-self:end"><button class="btn">Catat pemakaian</button></div>
      </form>
      @endif
    </div>
  </div>

  <div class="stack">
    @if ($wo->customer)
    <div class="card"><div class="card-head"><h3>Pelanggan</h3></div><div class="card-body">
      <a href="{{ route('app.customers.show', $wo->customer) }}"><strong>{{ $wo->customer->name }}</strong></a> <span class="mono small faint">{{ $wo->customer->customer_code }}</span>
      <div class="small"><a href="https://wa.me/{{ \App\Services\NotificationService::normalizeNumber($wo->customer->phone) }}" target="_blank" rel="noopener">{{ $wo->customer->phone }}</a></div>
      <div class="small muted">{{ $wo->customer->address }}</div>
      @if ($wo->customer->landmark)<div class="small muted">Patokan: {{ $wo->customer->landmark }}</div>@endif
      @if ($wo->service)<div class="small" style="margin-top:.5rem">{{ $wo->service->package?->name }}@if ($wo->service->odpPort)<br><span class="faint">{{ $wo->service->odpPort->odp->code }} · port {{ $wo->service->odpPort->port_number }}</span>@endif</div>@endif
    </div></div>
    @endif
    <div class="card"><div class="card-body"><dl class="kv" style="grid-template-columns:100px 1fr">
      <dt>Teknisi</dt><dd>@forelse ($wo->technicians as $t){{ $t->name }}{{ $t->pivot->role === 'lead' ? ' (PJ)' : '' }}<br>@empty - @endforelse</dd>
      <dt>Prioritas</dt><dd>{{ \App\Models\Ticket::PRIORITIES[$wo->priority] }}</dd>
      @if ($wo->ticket)<dt>Tiket</dt><dd><a href="{{ route('app.tickets.show', $wo->ticket) }}">{{ $wo->ticket->number }}</a></dd>@endif
      <dt>Mulai</dt><dd>{{ $wo->started_at?->format('d/m/Y H:i') ?? '-' }}</dd>
      @if ($wo->description)<dt>Instruksi</dt><dd style="white-space:pre-wrap">{{ $wo->description }}</dd>@endif
    </dl></div></div>
    <div class="card"><div class="card-head"><h3>Riwayat</h3></div><div class="card-body">
      @foreach ($wo->history as $h)<div class="small" style="margin-bottom:.5rem"><strong>{{ \App\Models\WorkOrder::STATUSES[$h->to_status] ?? $h->to_status }}</strong> · {{ $h->user?->name ?? 'Sistem' }}<div class="faint">{{ $h->created_at->format('d/m/Y H:i') }}{{ $h->note ? ' — '.$h->note : '' }}</div></div>@endforeach
    </div></div>
  </div>
</div>
@endsection
