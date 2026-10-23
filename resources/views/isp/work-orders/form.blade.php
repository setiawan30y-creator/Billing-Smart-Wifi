@extends('layouts.app')
@section('title', $wo->exists ? 'Ubah work order' : 'Work order baru')
@section('content')
<div class="page-head"><h1>{{ $wo->exists ? 'Ubah '.$wo->number : 'Work order baru' }}</h1><a href="{{ $wo->exists ? route('app.work-orders.show', $wo) : route('app.work-orders.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ $wo->exists ? route('app.work-orders.update', $wo) : route('app.work-orders.store') }}" class="card" style="max-width:920px"
  x-data="{ customer: '{{ old('customer_id', $wo->customer_id) }}', services: {{ \Illuminate\Support\Js::from($services->map(fn ($s) => ['id' => $s->id, 'customer_id' => $s->customer_id, 'label' => $s->package?->name])) }} }">
  @csrf @if ($wo->exists) @method('PUT') @endif
  <input type="hidden" name="ticket_id" value="{{ old('ticket_id', $wo->ticket_id) }}">
  <div class="card-body form-grid">
    <div class="field"><label class="req">Jenis</label><select name="type" class="input" @disabled($wo->exists)>@foreach (\App\Models\WorkOrder::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $wo->type) === $k)>{{ $l }}</option>@endforeach</select>@if ($wo->exists)<input type="hidden" name="type" value="{{ $wo->type }}">@endif</div>
    <div class="field"><label class="req">Prioritas</label><select name="priority" class="input">@foreach (\App\Models\Ticket::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected(old('priority', $wo->priority) === $k)>{{ $l }}</option>@endforeach</select></div>
    <x-field name="title" label="Judul pekerjaan" :value="$wo->title" required full placeholder="mis. Pemasangan baru paket Home 20"/>
    <div class="field"><label>Pelanggan</label><select name="customer_id" class="input" x-model="customer"><option value="">— Tanpa pelanggan —</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->customer_code }})</option>@endforeach</select></div>
    <div class="field"><label>Layanan</label><select name="customer_service_id" class="input"><option value="">—</option>
      <template x-for="s in services.filter(s => String(s.customer_id) === String(customer))" :key="s.id"><option :value="s.id" x-text="s.label" :selected="String(s.id) === '{{ old('customer_service_id', $wo->customer_service_id) }}'"></option></template>
    </select></div>
    <x-field name="scheduled_at" label="Jadwal mulai" type="datetime-local" :value="$wo->scheduled_at?->format('Y-m-d\TH:i')"/>
    <x-field name="scheduled_end_at" label="Perkiraan selesai" type="datetime-local" :value="$wo->scheduled_end_at?->format('Y-m-d\TH:i')"/>
    <div class="field full"><label>Teknisi (yang pertama dicentang = penanggung jawab)</label>
      <div class="grid grid-3">@foreach ($technicians as $t)<label class="check"><input type="checkbox" name="technicians[]" value="{{ $t->id }}" @checked(in_array($t->id, old('technicians', $selectedTechs)))> {{ $t->name }}</label>@endforeach</div>
      @if ($technicians->isEmpty())<div class="hint">Belum ada pengguna dengan role Teknisi/NOC. Tambahkan di menu Pengguna & Role.</div>@endif</div>
    <x-field name="description" label="Deskripsi / instruksi" type="textarea" :value="$wo->description" full rows="4"/>
    @unless ($wo->exists)<p class="full small muted" style="margin:0">Checklist standar dibuat otomatis sesuai jenis pekerjaan. Benturan jadwal teknisi akan ditampilkan sebagai peringatan setelah disimpan.</p>@endunless
  </div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan</button></div>
</form>
@endsection
