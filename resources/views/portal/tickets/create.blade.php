@extends('portal.layout')
@section('title', 'Tiket baru')
@section('content')
<h1>Tiket baru</h1>
<div class="card"><form method="POST" enctype="multipart/form-data" action="{{ route('portal.tickets.store', $tenant->code) }}" class="card-body stack">@csrf
  <div class="field"><label class="req">Kategori</label><select name="category" class="input">@foreach (\App\Models\Ticket::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>@endforeach</select></div>
  <x-field name="subject" label="Judul" required placeholder="mis. Internet mati sejak pagi"/>
  <x-field name="message" label="Ceritakan kendalanya" type="textarea" required rows="5" placeholder="Sejak kapan, lampu modem menyala apa saja, dsb."/>
  <x-field name="attachment" label="Lampiran (foto/PDF, opsional)" type="file" accept="image/*,application/pdf"/>
  <button class="btn btn-primary btn-block">Kirim tiket</button>
</form></div>
@endsection
