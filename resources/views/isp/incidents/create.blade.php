@extends('layouts.app')
@section('title', 'Insiden baru')
@section('content')
<div class="page-head"><h1>Catat gangguan massal</h1><a href="{{ route('app.incidents.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ route('app.incidents.store') }}" class="card" style="max-width:960px">@csrf
  <div class="card-body form-grid">
    <x-field name="title" label="Judul" required full placeholder="mis. Gangguan layanan area Blok A"/>
    <div class="field"><label class="req">Tingkat</label><select name="severity" class="input">@foreach (\App\Models\NetworkIncident::SEVERITIES as $k => $l)<option value="{{ $k }}" @selected($k === 'major')>{{ $l }}</option>@endforeach</select></div>
    <x-field name="started_at" label="Mulai" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required/>
    <x-field name="estimated_recovery_at" label="Estimasi pulih (opsional)" type="datetime-local"/>
    <x-field name="description" label="Deskripsi (fakta yang diketahui)" type="textarea" full rows="3" placeholder="Hindari menyebut penyebab sebelum terkonfirmasi."/>
    <div class="field full"><label class="req">Cakupan terdampak</label>@include('isp.incidents._affected')</div>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan insiden</button></div>
</form>
@endsection
