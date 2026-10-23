@extends('layouts.app')
@section('title', 'Log WhatsApp')
@section('content')
<div class="page-head"><div><h1>Log pengiriman WhatsApp</h1><p>Status antre, terkirim, dan gagal (dengan retry otomatis).</p></div><a href="{{ route('app.templates.index') }}" class="btn">Template</a></div>
<div class="card">
  <form class="toolbar" method="GET"><select name="status" class="input"><option value="">Semua status</option>@foreach (['queued','sent','delivered','read','failed'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select><button class="btn">Filter</button></form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Waktu</th><th>Event</th><th>Tujuan</th><th>Pesan</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($logs as $l)
      <tr><td class="small nowrap">{{ $l->created_at->format('d/m/Y H:i') }}</td><td class="small mono">{{ $l->event }}</td><td class="mono small">{{ $l->recipient }}</td>
        <td class="small muted" style="max-width:380px">{{ \Illuminate\Support\Str::limit($l->message, 100) }}@if ($l->error)<div style="color:var(--danger)">{{ $l->error }}</div>@endif</td>
        <td><x-badge :status="$l->status"/><div class="small faint">{{ $l->attempts }}x</div></td></tr>
    @empty <tr><td colspan="5" class="empty">Belum ada log.</td></tr> @endforelse
    </tbody></table></div>
  {{ $logs->links() }}
</div>
@endsection
