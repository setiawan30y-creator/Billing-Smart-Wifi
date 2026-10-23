@extends('layouts.app')
@section('title', $incident->number)
@section('content')
<div class="page-head">
  <div><h1>{{ $incident->title }} <x-badge :status="$incident->statusBadge()" :label="\App\Models\NetworkIncident::STATUSES[$incident->status]"/></h1>
    <p><span class="mono">{{ $incident->number }}</span> · {{ \App\Models\NetworkIncident::SEVERITIES[$incident->severity] }} · mulai {{ $incident->started_at->format('d/m/Y H:i') }}{{ $incident->resolved_at ? ' · pulih '.$incident->resolved_at->format('d/m/Y H:i') : '' }}</p></div>
  <div class="actions"><a href="{{ route('app.incidents.index') }}" class="btn">Semua insiden</a>
    @can('announcements.send')<a href="{{ route('app.announcements.create', ['incident' => $incident->id]) }}" class="btn btn-primary"><x-icon name="megaphone"/> Kirim pengumuman</a>@endcan</div>
</div>
@if ($incident->auto_detected)<div class="alert alert-info">Terdeteksi otomatis: {{ $incident->detection_reason }}. Verifikasi di lapangan sebelum menyimpulkan penyebab.</div>@endif
<div class="grid grid-main">
  <div class="stack">
    <div class="card"><div class="card-body"><dl class="kv">
      <dt>Deskripsi</dt><dd style="white-space:pre-wrap">{{ $incident->description ?: '-' }}</dd>
      <dt>Penyebab</dt><dd>{{ $incident->confirmed_cause ?: 'Belum dikonfirmasi' }}</dd>
      <dt>Estimasi pulih</dt><dd>{{ $incident->estimated_recovery_at?->format('d/m/Y H:i') ?? '-' }}</dd>
      <dt>Cakupan</dt><dd>{{ $incident->devices->map->label()->implode(', ') }}</dd>
    </dl></div></div>
    <div class="card"><div class="card-head"><h3>Pelanggan terdampak ({{ $customers->count() }})</h3></div><div class="table-wrap"><table class="table"><tbody>
      @forelse ($customers as $c)<tr><td><a href="{{ route('app.customers.show', $c->customer_id) }}">{{ $c->customer?->name }}</a> <span class="mono small faint">{{ $c->customer?->customer_code }}</span></td><td class="small">{{ $c->via }}</td><td class="small">{{ $c->customer?->phone }}</td></tr>
      @empty <tr><td class="empty">Tidak ada pelanggan terpetakan pada cakupan ini.</td></tr> @endforelse
    </tbody></table></div></div>
    <details class="card"><summary class="card-body" style="cursor:pointer;font-weight:600">Ubah cakupan terdampak</summary>
      <form method="POST" action="{{ route('app.incidents.affected', $incident) }}" class="card-body" style="border-top:1px solid var(--border)">@csrf
        @include('isp.incidents._affected', ['selected' => $incident->devices->map(fn ($d) => $d->affected_type.':'.$d->affected_id)->all()])
        <div style="margin-top:.8rem"><button class="btn">Hitung ulang terdampak</button></div>
      </form></details>
  </div>
  <div class="stack">
    <div class="card"><div class="card-head"><h3>Pembaruan</h3></div>
      <form method="POST" action="{{ route('app.incidents.update', $incident) }}" class="card-body stack">@csrf
        <div class="field"><label>Status</label><select name="status" class="input">@foreach (\App\Models\NetworkIncident::STATUSES as $k => $l)<option value="{{ $k }}" @selected($incident->status === $k)>{{ $l }}</option>@endforeach</select></div>
        <x-field name="note" label="Catatan pembaruan" type="textarea" rows="3" required/>
        <x-field name="confirmed_cause" label="Penyebab terkonfirmasi (isi hanya bila sudah terbukti)" :value="$incident->confirmed_cause"/>
        <x-field name="estimated_recovery_at" label="Estimasi pulih" type="datetime-local" :value="$incident->estimated_recovery_at?->format('Y-m-d\TH:i')"/>
        <button class="btn btn-primary">Simpan pembaruan</button>
      </form></div>
    <div class="card"><div class="card-body">
      @foreach ($incident->updates as $u)<div class="small" style="margin-bottom:.7rem"><strong>{{ \App\Models\NetworkIncident::STATUSES[$u->status] }}</strong> · {{ $u->user?->name ?? 'Sistem' }} · <span class="faint">{{ $u->created_at->format('d/m H:i') }}</span><div style="white-space:pre-wrap">{{ $u->note }}</div></div>@endforeach
    </div></div>
  </div>
</div>
@endsection
