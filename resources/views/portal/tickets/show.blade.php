@extends('portal.layout')
@section('title', $ticket->number)
@section('content')
<div class="page-head"><div><h1 style="margin:0">{{ $ticket->subject }}</h1><p class="small">{{ $ticket->number }} · {{ \App\Models\Ticket::CATEGORIES[$ticket->category] }}</p></div><x-badge :status="$ticket->statusBadge()" :label="\App\Models\Ticket::STATUSES[$ticket->status]"/></div>
<div class="card" style="margin-bottom:1rem"><div class="card-body">
  @foreach ($messages as $m)
    <div class="bubble {{ $m->author_type === 'customer' ? 'me' : ($m->author_type === 'system' ? 'sys' : 'them') }}">
      @if ($m->author_type !== 'system')<div class="small faint" style="margin-bottom:.2rem">{{ $m->author_type === 'customer' ? 'Anda' : $tenant->displayName() }} · {{ $m->created_at->format('d/m H:i') }}</div>@endif
      {{ $m->body }}
      @if ($m->attachment_path)<div style="margin-top:.4rem"><a class="small" target="_blank" href="{{ route('portal.tickets.attachment', [$tenant->code, $ticket->id, $m->id]) }}">📎 Lampiran</a></div>@endif
    </div>
  @endforeach
</div></div>
@if ($ticket->status !== 'closed')
<div class="card"><form method="POST" enctype="multipart/form-data" action="{{ route('portal.tickets.reply', [$tenant->code, $ticket->id]) }}" class="card-body stack">@csrf
  <x-field name="message" label="Balas" type="textarea" required rows="3"/>
  <x-field name="attachment" label="Lampiran (opsional)" type="file" accept="image/*,application/pdf"/>
  <button class="btn btn-primary">Kirim balasan</button>
</form></div>
@else <p class="muted small">Tiket ini sudah ditutup. Buat tiket baru bila masih ada kendala.</p> @endif
@endsection
