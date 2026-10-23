@extends('layouts.app')
@section('title', $a->title)
@section('content')
<div class="page-head"><div><h1>{{ $a->title }} <x-badge :status="$a->status === 'sent' ? 'done' : 'pending'" :label="$a->status === 'sent' ? 'Terkirim' : 'Draf'"/></h1><p>{{ \App\Models\ServiceAnnouncement::MODES[$a->target_mode] }}{{ $a->incident ? ' · '.$a->incident->number : '' }}</p></div><a href="{{ route('app.announcements.index') }}" class="btn">Kembali</a></div>
<div class="grid grid-main">
  <div class="card">
    @if ($a->status === 'draft')
      <div class="card-head"><h3>Preview penerima ({{ $recipients->count() }})</h3></div>
      <div class="table-wrap" style="max-height:480px;overflow:auto"><table class="table"><tbody>
        @forelse ($recipients as $c)<tr><td>{{ $c->name }} <span class="mono small faint">{{ $c->customer_code }}</span></td><td class="small mono">{{ $c->phone ?: 'tanpa nomor' }}</td></tr>
        @empty <tr><td class="empty">Tidak ada penerima untuk target ini.</td></tr> @endforelse
      </tbody></table></div>
    @else
      <div class="card-head"><h3>Pengiriman ({{ $a->recipient_count }})</h3><a href="{{ route('app.templates.logs') }}" class="small">Log WhatsApp</a></div>
      <div class="table-wrap"><table class="table"><tbody>
        @foreach ($deliveries as $d)<tr><td>{{ $d->customer?->name }}</td><td><x-badge :status="$d->log?->status ?? ($d->status === 'portal_only' ? 'muted' : 'void')" :label="$d->log?->status ?? ['skipped' => 'dilewati', 'portal_only' => 'portal saja', 'queued' => 'antre'][$d->status]"/></td></tr>@endforeach
      </tbody></table></div>{{ $deliveries->links() }}
    @endif
  </div>
  <div class="stack">
    <div class="card"><div class="card-head"><h3>Isi pesan</h3></div><div class="card-body"><div style="white-space:pre-wrap;background:var(--success-50);padding:.9rem;border-radius:10px">{{ $a->title }}
{{ $a->body }}</div>
      <p class="small muted" style="margin:.6rem 0 0">WhatsApp: {{ $a->send_whatsapp ? 'ya' : 'tidak' }} · Portal: {{ $a->show_in_portal ? 'ya' : 'tidak' }}{{ $a->visible_until ? ' s/d '.$a->visible_until->format('d/m H:i') : '' }}</p></div></div>
    @if ($a->status === 'draft' && $recipients->count())
      <form method="POST" action="{{ route('app.announcements.send', $a) }}" class="card card-body">@csrf<button class="btn btn-primary btn-block"><x-icon name="megaphone"/> Kirim ke {{ $recipients->count() }} pelanggan</button><p class="small muted" style="margin:.5rem 0 0">Satu pesan per pelanggan (deduplikasi), dikirim bertahap sesuai rate limit gateway.</p></form>
    @endif
  </div>
</div>
@endsection
