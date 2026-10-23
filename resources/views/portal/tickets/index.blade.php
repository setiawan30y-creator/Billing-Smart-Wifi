@extends('portal.layout')
@section('title', 'Bantuan')
@section('content')
<div class="page-head"><h1>Tiket bantuan</h1><a href="{{ route('portal.tickets.create', $tenant->code) }}" class="btn btn-primary"><x-icon name="plus"/> Tiket baru</a></div>
<div class="card">
  @forelse ($tickets as $t)
    <a class="list-item" href="{{ route('portal.tickets.show', [$tenant->code, $t->id]) }}"><div><strong>{{ $t->subject }}</strong><div class="small muted">{{ $t->number }} · {{ \App\Models\Ticket::CATEGORIES[$t->category] }} · {{ $t->created_at->format('d/m/Y') }}</div></div>
      <x-badge :status="$t->statusBadge()" :label="\App\Models\Ticket::STATUSES[$t->status]"/></a>
  @empty <div class="card-body muted">Belum ada tiket. Ada kendala? Buat tiket baru.</div> @endforelse
  {{ $tickets->links() }}
</div>
@endsection
