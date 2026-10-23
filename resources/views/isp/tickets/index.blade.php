@extends('layouts.app')
@section('title', 'Tiket Bantuan')
@section('content')
<div class="page-head"><div><h1>Tiket Bantuan</h1><p>Laporan pelanggan dari portal maupun yang dicatat staf.</p></div>
  @can('tickets.manage')<a href="{{ route('app.tickets.create') }}" class="btn btn-primary"><x-icon name="plus"/> Tiket</a>@endcan</div>
<div class="actions" style="margin-bottom:1rem">
  @foreach (['aktif' => 'Aktif', 'semua' => 'Semua'] + \App\Models\Ticket::STATUSES as $k => $l)
    <a href="{{ route('app.tickets.index', ['status' => $k]) }}" class="badge badge-{{ $status === $k ? 'primary' : 'muted' }}">{{ $l }}@if (isset($counts[$k])): {{ $counts[$k] }}@endif</a>
  @endforeach
</div>
<div class="card">
  <form class="toolbar" method="GET"><input type="hidden" name="status" value="{{ $status }}">
    <input class="input grow" name="q" value="{{ request('q') }}" placeholder="Cari nomor, judul, atau pelanggan">
    <select name="category" class="input"><option value="">Semua kategori</option>@foreach (\App\Models\Ticket::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach</select>
    <label class="check small"><input type="checkbox" name="mine" value="1" @checked(request('mine'))> Ditugaskan ke saya</label>
    <button class="btn"><x-icon name="search"/> Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Tiket</th><th>Pelanggan</th><th>Kategori</th><th>Prioritas</th><th>Petugas</th><th>Status</th><th>Dibuat</th></tr></thead>
    <tbody>
    @forelse ($tickets as $t)
      <tr><td><a href="{{ route('app.tickets.show', $t) }}"><strong>{{ $t->subject }}</strong></a><div class="small faint mono">{{ $t->number }} · {{ $t->source === 'portal' ? 'dari portal' : 'staf' }}</div></td>
        <td class="small">{{ $t->customer?->name ?? '-' }}</td><td class="small">{{ \App\Models\Ticket::CATEGORIES[$t->category] }}</td>
        <td><x-badge :status="in_array($t->priority, ['high', 'urgent']) ? 'failed' : 'muted'" :label="\App\Models\Ticket::PRIORITIES[$t->priority]"/></td>
        <td class="small">{{ $t->assignee?->name ?? '-' }}</td>
        <td><x-badge :status="$t->statusBadge()" :label="\App\Models\Ticket::STATUSES[$t->status]"/></td>
        <td class="small nowrap">{{ $t->created_at->format('d/m/Y H:i') }}</td></tr>
    @empty <tr><td colspan="7" class="empty">Tidak ada tiket.</td></tr> @endforelse
    </tbody></table></div>
  {{ $tickets->links() }}
</div>
@endsection
