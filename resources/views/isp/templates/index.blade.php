@extends('layouts.app')
@section('title', 'Template WhatsApp')
@section('content')
<div class="page-head"><div><h1>Pengaturan → WhatsApp → Template Pesan</h1><p>Template notifikasi per tenant.</p></div><a href="{{ route('app.templates.logs') }}" class="btn"><x-icon name="list"/> Log pengiriman</a></div>
@if ($driver === 'log')<div class="alert alert-warning">Driver WhatsApp saat ini <strong>log</strong>: pesan hanya dicatat, tidak benar-benar terkirim. Atur <span class="mono">WHATSAPP_DRIVER=http</span> dan URL/token gateway di .env setelah API gateway diverifikasi.</div>@endif
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Event</th><th>Isi</th><th>Status</th><th></th></tr></thead>
  <tbody>
  @foreach ($templates as $t)
    <tr><td><strong>{{ \App\Models\NotificationTemplate::EVENTS[$t->event] }}</strong><div class="small faint mono">{{ $t->event }}</div></td>
      <td class="small muted" style="max-width:460px">{{ \Illuminate\Support\Str::limit($t->body, 120) }}</td>
      <td>@if ($t->is_enabled)<x-badge status="active" label="Aktif"/>@else<x-badge status="void" label="Nonaktif"/>@endif</td>
      <td class="right"><a class="btn btn-sm" href="{{ route('app.templates.edit', $t) }}">Ubah</a></td></tr>
  @endforeach
  </tbody></table></div></div>
@endsection
