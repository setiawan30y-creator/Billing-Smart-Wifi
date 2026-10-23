@extends('layouts.app')
@section('title', 'Audit Log')
@section('content')
<div class="page-head"><div><h1>Audit Log</h1><p>Jejak tindakan: pelaku, waktu, nilai sebelum/sesudah. Log tidak dapat diubah.</p></div></div>
<div class="card">
  <form class="toolbar" method="GET">
    @if ($platform)<select name="tenant" class="input"><option value="">Semua tenant</option>@foreach ($tenants as $t)<option value="{{ $t->id }}" @selected(request('tenant') == $t->id)>{{ $t->name }}</option>@endforeach</select>@endif
    <input name="action" class="input" value="{{ request('action') }}" placeholder="Awalan tindakan, mis. payment.">
    <button class="btn">Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Waktu</th>@if ($platform)<th>Tenant</th>@endif<th>Tindakan</th><th>Oleh</th><th>Perubahan</th><th>IP</th></tr></thead>
    <tbody>
    @forelse ($logs as $l)
      <tr><td class="small nowrap">{{ $l->created_at->format('d/m/Y H:i:s') }}</td>
        @if ($platform)<td class="small">{{ $l->tenant?->name ?? 'Platform' }}</td>@endif
        <td class="mono small">{{ $l->action }}@if ($l->auditable_type)<div class="faint">{{ class_basename($l->auditable_type) }} #{{ $l->auditable_id }}</div>@endif @if ($l->reason)<div class="muted">Alasan: {{ $l->reason }}</div>@endif</td>
        <td class="small">{{ $l->user?->name ?? 'Sistem' }}</td>
        <td class="small" style="max-width:360px">
          @if ($l->before || $l->after)
            <details><summary class="muted" style="cursor:pointer">lihat</summary>
              @if ($l->before)<div class="faint">Sebelum:</div><pre class="mono" style="white-space:pre-wrap;margin:0">{{ json_encode($l->before, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif
              @if ($l->after)<div class="faint">Sesudah:</div><pre class="mono" style="white-space:pre-wrap;margin:0">{{ json_encode($l->after, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif
            </details>
          @endif
        </td>
        <td class="small mono">{{ $l->ip_address }}</td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada log.</td></tr> @endforelse
    </tbody></table></div>
  {{ $logs->links() }}
</div>
@endsection
