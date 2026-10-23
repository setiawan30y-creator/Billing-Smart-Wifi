@extends('layouts.app')
@section('title', 'Alarm monitoring')
@section('content')
<div class="page-head"><div><h1>Alarm & gangguan perangkat</h1><p>Alarm dideduplikasi per target & metrik, otomatis pulih saat normal.</p></div><a href="{{ route('app.monitoring.index') }}" class="btn">Monitoring</a></div>
<div class="card">
  <div class="toolbar">@foreach (['' => 'Semua', 'open' => 'Terbuka', 'acknowledged' => 'Di-acknowledge', 'resolved' => 'Pulih'] as $k => $l)<a href="{{ route('app.monitoring.alerts', array_filter(['status' => $k])) }}" class="badge badge-{{ request('status', '') === $k ? 'primary' : 'muted' }}">{{ $l }}</a>@endforeach</div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Mulai</th><th>Target</th><th>Alarm</th><th>Status</th><th>Durasi</th><th></th></tr></thead><tbody>
    @forelse ($alerts as $a)<tr><td class="small nowrap">{{ $a->started_at->format('d/m/Y H:i') }}</td><td><a href="{{ route('app.monitoring.show', $a->monitoring_target_id) }}">{{ $a->target?->name }}</a></td>
      <td class="small"><x-badge :status="$a->severity === 'critical' ? 'failed' : 'pending'" :label="\App\Models\MonitoringAlert::METRICS[$a->metric]"/> {{ $a->message }}</td>
      <td class="small">{{ ['open' => 'Terbuka', 'acknowledged' => 'Di-acknowledge', 'resolved' => 'Pulih'][$a->status] }}@if ($a->acknowledger)<div class="faint">{{ $a->acknowledger->name }}{{ $a->ack_note ? ': '.$a->ack_note : '' }}</div>@endif</td>
      <td class="small">{{ $a->started_at->diffForHumans($a->resolved_at ?? now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]) }}</td>
      <td class="right">@can('monitoring.alerts.acknowledge')@if ($a->status === 'open')<form method="POST" action="{{ route('app.monitoring.ack', $a) }}" style="display:flex;gap:.3rem;justify-content:flex-end">@csrf<input name="note" class="input" style="padding:.3rem .5rem;max-width:180px" placeholder="Catatan"><button class="btn btn-sm">Ack</button></form>@endif @endcan</td></tr>
    @empty <tr><td colspan="6" class="empty">Tidak ada alarm.</td></tr> @endforelse
  </tbody></table></div>
  {{ $alerts->links() }}
</div>
@endsection
