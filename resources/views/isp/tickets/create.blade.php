@extends('layouts.app')
@section('title', 'Tiket baru')
@section('content')
<div class="page-head"><h1>Tiket baru</h1><a href="{{ route('app.tickets.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ route('app.tickets.store') }}" class="card" style="max-width:820px">@csrf
  <div class="card-body form-grid">
    <div class="field"><label>Pelanggan</label><select name="customer_id" class="input"><option value="">— Tanpa pelanggan (internal) —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $selected) == $c->id)>{{ $c->name }} ({{ $c->customer_code }})</option>@endforeach</select></div>
    <div class="field"><label class="req">Kategori</label><select name="category" class="input">@foreach (\App\Models\Ticket::CATEGORIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label class="req">Prioritas</label><select name="priority" class="input">@foreach (\App\Models\Ticket::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected($k === 'normal')>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label>Tugaskan ke</label><select name="assigned_to" class="input"><option value="">—</option>@foreach ($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
    <x-field name="subject" label="Judul" required full/>
    <x-field name="message" label="Deskripsi" type="textarea" required full rows="5"/>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Buat tiket</button></div>
</form>
@endsection
