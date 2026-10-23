@extends('layouts.app')
@section('title', $ticket->number)
@section('content')
<style>.bubble{padding:.75rem .9rem;border-radius:12px;margin-bottom:.6rem;white-space:pre-wrap;word-break:break-word}.bubble.cust{background:var(--surface-2);border:1px solid var(--border);margin-right:12%}.bubble.staff{background:var(--primary-50);margin-left:12%}.bubble.internal{background:var(--warning-50);margin-left:12%}.bubble.sys{text-align:center;color:var(--text-3);font-size:.8rem;padding:.2rem}</style>
<div class="page-head">
  <div><h1>{{ $ticket->subject }} <x-badge :status="$ticket->statusBadge()" :label="\App\Models\Ticket::STATUSES[$ticket->status]"/></h1>
    <p class="mono">{{ $ticket->number }} · {{ \App\Models\Ticket::CATEGORIES[$ticket->category] }} · {{ $ticket->source === 'portal' ? 'dari portal pelanggan' : 'dicatat staf' }} · {{ $ticket->created_at->format('d/m/Y H:i') }}</p></div>
  <div class="actions">
    <a href="{{ route('app.tickets.index') }}" class="btn">Semua tiket</a>
    @if (Route::has('app.work-orders.create') && $ticket->customer_id)
      @can('work_orders.manage')<a href="{{ route('app.work-orders.create', ['ticket' => $ticket->id]) }}" class="btn"><x-icon name="tool"/> Buat work order</a>@endcan
    @endif
  </div>
</div>
<div class="grid grid-main">
  <div class="stack">
    <div class="card"><div class="card-body">
      @foreach ($messages as $m)
        <div class="bubble {{ $m->author_type === 'customer' ? 'cust' : ($m->author_type === 'system' ? 'sys' : ($m->is_internal ? 'internal' : 'staff')) }}">
          @if ($m->author_type !== 'system')<div class="small faint" style="margin-bottom:.2rem">{{ $m->author_type === 'customer' ? ($ticket->customer?->name ?? 'Pelanggan') : $m->authorName() }}{{ $m->is_internal ? ' · catatan internal' : '' }} · {{ $m->created_at->format('d/m H:i') }}</div>@endif
          {{ $m->body }}
          @if ($m->attachment_path)<div style="margin-top:.4rem"><a class="small" target="_blank" href="{{ route('app.tickets.attachment', [$ticket, $m]) }}">📎 Lampiran</a></div>@endif
        </div>
      @endforeach
    </div></div>
    @can('tickets.manage')
    <div class="card"><form method="POST" enctype="multipart/form-data" action="{{ route('app.tickets.reply', $ticket) }}" class="card-body stack">@csrf
      <x-field name="message" label="Balasan" type="textarea" required rows="4"/>
      <div class="form-grid">
        <x-field name="attachment" label="Lampiran (opsional)" type="file" accept="image/*,application/pdf"/>
        <div class="field"><label>Ubah status sekalian</label><select name="status" class="input"><option value="">— Tetap —</option>@foreach (\App\Models\Ticket::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
      </div>
      <label class="check"><input type="checkbox" name="internal" value="1"> Catatan internal (tidak terlihat pelanggan, tanpa notifikasi)</label>
      <div><button class="btn btn-primary">Kirim</button></div>
    </form></div>
    @endcan
  </div>
  <div class="stack">
    @can('tickets.manage')
    <div class="card"><div class="card-head"><h3>Penanganan</h3></div>
      <form method="POST" action="{{ route('app.tickets.update', $ticket) }}" class="card-body stack">@csrf @method('PUT')
        <div class="field"><label>Status</label><select name="status" class="input">@foreach (\App\Models\Ticket::STATUSES as $k => $l)<option value="{{ $k }}" @selected($ticket->status === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Prioritas</label><select name="priority" class="input">@foreach (\App\Models\Ticket::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected($ticket->priority === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Petugas</label><select name="assigned_to" class="input"><option value="">—</option>@foreach ($staff as $u)<option value="{{ $u->id }}" @selected($ticket->assigned_to === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        <button class="btn">Simpan</button>
      </form></div>
    @endcan
    @if ($ticket->customer)
    <div class="card"><div class="card-head"><h3>Pelanggan</h3></div><div class="card-body">
      <a href="{{ route('app.customers.show', $ticket->customer) }}"><strong>{{ $ticket->customer->name }}</strong></a> <span class="mono small faint">{{ $ticket->customer->customer_code }}</span>
      <div class="small muted">{{ $ticket->customer->phone }}</div><div class="small muted">{{ $ticket->customer->address }}</div>
      @foreach ($ticket->customer->services as $s)<div class="small" style="margin-top:.4rem">{{ $s->package?->name }} · <x-badge :status="$s->status" :label="\App\Models\CustomerService::STATUSES[$s->status]"/>@if ($s->odpPort)<div class="faint">{{ $s->odpPort->odp->code }} · port {{ $s->odpPort->port_number }}</div>@endif</div>@endforeach
    </div></div>
    @endif
    <div class="card"><div class="card-body small"><dl class="kv" style="grid-template-columns:110px 1fr">
      <dt>Respons pertama</dt><dd>{{ $ticket->first_response_at?->format('d/m/Y H:i') ?? '-' }}</dd>
      <dt>Selesai</dt><dd>{{ $ticket->resolved_at?->format('d/m/Y H:i') ?? '-' }}</dd>
    </dl></div></div>
  </div>
</div>
@endsection
