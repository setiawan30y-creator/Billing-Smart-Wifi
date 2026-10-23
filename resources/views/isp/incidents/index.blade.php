@extends('layouts.app')
@section('title', 'Gangguan Massal')
@section('content')
<div class="page-head"><div><h1>Gangguan Massal</h1><p>Pelanggan terdampak dihitung dari topologi OLT → ODC → ODP → port.</p></div>
  <div class="actions"><a href="{{ route('app.incidents.index', ['status' => request('status', 'aktif') === 'aktif' ? 'semua' : 'aktif']) }}" class="btn">{{ request('status', 'aktif') === 'aktif' ? 'Tampilkan semua' : 'Hanya aktif' }}</a><a href="{{ route('app.incidents.create') }}" class="btn btn-primary"><x-icon name="plus"/> Insiden</a></div></div>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Insiden</th><th>Tingkat</th><th class="num">Terdampak</th><th>Mulai</th><th>Status</th></tr></thead><tbody>
  @forelse ($incidents as $i)<tr><td><a href="{{ route('app.incidents.show', $i) }}"><strong>{{ $i->title }}</strong></a><div class="small faint"><span class="mono">{{ $i->number }}</span>{{ $i->auto_detected ? ' · terdeteksi otomatis' : '' }}</div></td>
    <td><x-badge :status="$i->severity === 'critical' ? 'failed' : ($i->severity === 'major' ? 'pending' : 'muted')" :label="\App\Models\NetworkIncident::SEVERITIES[$i->severity]"/></td>
    <td class="num">{{ $i->affected_customers_count }}</td><td class="small nowrap">{{ $i->started_at->format('d/m/Y H:i') }}</td>
    <td><x-badge :status="$i->statusBadge()" :label="\App\Models\NetworkIncident::STATUSES[$i->status]"/></td></tr>
  @empty <tr><td colspan="5" class="empty">Tidak ada insiden.</td></tr> @endforelse
  </tbody></table></div>{{ $incidents->links() }}</div>
@endsection
