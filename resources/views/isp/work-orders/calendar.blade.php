@extends('layouts.app')
@section('title', 'Jadwal Teknisi')
@section('content')
<style>.cal{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border-top:1px solid var(--border);border-left:1px solid var(--border)}.cal>div{border-right:1px solid var(--border);border-bottom:1px solid var(--border);min-height:110px;padding:.35rem;font-size:.78rem}.cal .hd{min-height:auto;background:var(--surface-2);font-weight:600;color:var(--text-3);text-align:center;text-transform:uppercase;font-size:.7rem}.cal .out{background:var(--surface-2);opacity:.6}.cal .today{box-shadow:inset 0 0 0 2px var(--primary)}.ev{display:block;padding:.2rem .35rem;border-radius:6px;margin-top:.25rem;background:var(--primary-50);color:var(--primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ev.done{background:var(--success-50);color:var(--success)}.ev.off{background:var(--warning-50);color:var(--warning)}@media(max-width:860px){.cal>div{min-height:70px;font-size:.68rem}}</style>
<div class="page-head">
  <div><h1>Jadwal Teknisi</h1><p>{{ $month->translatedFormat('F Y') }}</p></div>
  <div class="actions">
    <a class="btn" href="{{ route('app.schedule.index', array_filter(['month' => $month->subMonth()->format('Y-m'), 'tech' => $tech])) }}">‹</a>
    <a class="btn" href="{{ route('app.schedule.index', array_filter(['tech' => $tech])) }}">Bulan ini</a>
    <a class="btn" href="{{ route('app.schedule.index', array_filter(['month' => $month->addMonth()->format('Y-m'), 'tech' => $tech])) }}">›</a>
    @if ($manage)
    <form method="GET" style="display:flex;gap:.4rem"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><select name="tech" class="input" onchange="this.form.submit()"><option value="">Semua teknisi</option>@foreach ($technicians as $t)<option value="{{ $t->id }}" @selected($tech === $t->id)>{{ $t->name }}</option>@endforeach</select></form>
    @endif
  </div>
</div>
<div class="card"><div class="card-body" style="padding:0;overflow-x:auto"><div class="cal" style="min-width:640px">
  @foreach (['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d)<div class="hd">{{ $d }}</div>@endforeach
  @for ($d = $gridStart; $d->lte($gridEnd); $d = $d->addDay())
    @php $key = $d->toDateString(); @endphp
    <div class="{{ $d->month !== $month->month ? 'out' : '' }} {{ $d->isToday() ? 'today' : '' }}">
      <div style="font-weight:700">{{ $d->day }}</div>
      @foreach ($avail[$key] ?? [] as $a)<span class="ev off">{{ $a->user->name }}: {{ \App\Models\TechnicianAvailability::STATUSES[$a->status] }}</span>@endforeach
      @foreach ($wos[$key] ?? [] as $w)<a class="ev {{ $w->status === 'completed' ? 'done' : '' }}" href="{{ route('app.work-orders.show', $w) }}" title="{{ $w->title }} · {{ $w->technicians->pluck('name')->implode(', ') }}">{{ $w->scheduled_at->timezone($tz)->format('H:i') }} {{ $w->customer?->name ?? $w->title }}</a>@endforeach
    </div>
  @endfor
</div></div></div>
@if ($manage)
<div class="card" style="margin-top:1rem;max-width:760px"><div class="card-head"><h3>Catat libur / cuti teknisi</h3></div>
  <form method="POST" action="{{ route('app.schedule.availability') }}" class="card-body form-grid">@csrf
    <div class="field"><label>Teknisi</label><select name="user_id" class="input" required>@foreach ($technicians as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
    <x-field name="date" label="Tanggal" type="date" required/>
    <div class="field"><label>Status</label><select name="status" class="input">@foreach (\App\Models\TechnicianAvailability::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach<option value="available">Tersedia (hapus catatan)</option></select></div>
    <x-field name="note" label="Catatan"/>
    <div><button class="btn">Simpan</button></div>
  </form></div>
@endif
@endsection
