@extends('layouts.app')
@section('title', 'Pengumuman')
@section('content')
<div class="page-head"><div><h1>Pengumuman massal</h1><p>Informasi gangguan / pemeliharaan ke pelanggan via WhatsApp & portal.</p></div><a href="{{ route('app.announcements.create') }}" class="btn btn-primary"><x-icon name="plus"/> Pengumuman</a></div>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Judul</th><th>Target</th><th class="num">Penerima</th><th>Status</th><th>Dikirim</th></tr></thead><tbody>
  @forelse ($announcements as $a)<tr><td><a href="{{ route('app.announcements.show', $a) }}"><strong>{{ $a->title }}</strong></a>@if ($a->incident)<div class="small faint">{{ $a->incident->number }}</div>@endif</td>
    <td class="small">{{ \App\Models\ServiceAnnouncement::MODES[$a->target_mode] }}</td><td class="num">{{ $a->recipient_count ?: '—' }}</td>
    <td><x-badge :status="$a->status === 'sent' ? 'done' : 'pending'" :label="$a->status === 'sent' ? 'Terkirim' : 'Draf'"/></td><td class="small">{{ $a->sent_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
  @empty <tr><td colspan="5" class="empty">Belum ada pengumuman.</td></tr> @endforelse
  </tbody></table></div>{{ $announcements->links() }}</div>
@endsection
