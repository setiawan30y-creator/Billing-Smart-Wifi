@extends('layouts.app')
@section('title', $manage ? 'Work Order' : 'Tugas Saya')
@section('content')
<div class="page-head"><div><h1>{{ $manage ? 'Work Order' : 'Tugas Saya' }}</h1><p>Pemasangan, perbaikan, kunjungan, dan pencabutan.</p></div>
  <div class="actions"><a href="{{ route('app.schedule.index') }}" class="btn"><x-icon name="calendar"/> Kalender</a>@can('work_orders.manage')<a href="{{ route('app.work-orders.create') }}" class="btn btn-primary"><x-icon name="plus"/> Work order</a>@endcan</div></div>
<div class="actions" style="margin-bottom:1rem">
  @foreach (['aktif' => 'Aktif', 'semua' => 'Semua'] + \App\Models\WorkOrder::STATUSES as $k => $l)
    <a href="{{ route('app.work-orders.index', array_filter(['status' => $k, 'mine' => request('mine')])) }}" class="badge badge-{{ $status === $k ? 'primary' : 'muted' }}">{{ $l }}</a>
  @endforeach
</div>
<div class="card">
  <form class="toolbar" method="GET"><input type="hidden" name="status" value="{{ $status }}">
    <select name="type" class="input"><option value="">Semua jenis</option>@foreach (\App\Models\WorkOrder::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select>
    <input type="date" name="date" class="input" value="{{ request('date') }}">
    @if ($manage)<label class="check small"><input type="checkbox" name="mine" value="1" @checked(request('mine'))> Ditugaskan ke saya</label>@endif
    <button class="btn"><x-icon name="search"/> Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Work order</th><th>Pelanggan</th><th>Jadwal</th><th>Teknisi</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($workOrders as $w)
      <tr><td><a href="{{ route('app.work-orders.show', $w) }}"><strong>{{ $w->title }}</strong></a><div class="small faint"><span class="mono">{{ $w->number }}</span> · {{ \App\Models\WorkOrder::TYPES[$w->type] }} @if (in_array($w->priority, ['high', 'urgent']))<x-badge status="failed" :label="\App\Models\Ticket::PRIORITIES[$w->priority]"/>@endif</div></td>
        <td class="small">{{ $w->customer?->name ?? '-' }}<div class="faint">{{ \Illuminate\Support\Str::limit($w->customer?->address, 40) }}</div></td>
        <td class="small nowrap">{{ $w->scheduled_at?->format('d/m/Y H:i') ?? '-' }}</td>
        <td class="small">{{ $w->technicians->pluck('name')->implode(', ') ?: '-' }}</td>
        <td><x-badge :status="$w->statusBadge()" :label="\App\Models\WorkOrder::STATUSES[$w->status]"/></td></tr>
    @empty <tr><td colspan="5" class="empty">Tidak ada work order.</td></tr> @endforelse
    </tbody></table></div>
  {{ $workOrders->links() }}
</div>
@endsection
