@extends('layouts.app')
@section('title', 'Penugasan penagihan')
@section('content')
<div class="page-head"><div><h1>Penugasan penagihan</h1><p>Pembayaran pelanggan yang diverifikasi dikomisikan ke kolektor yang ditugaskan (atau ke kolektor yang mencatat pembayaran).</p></div><a href="{{ route('app.commissions.index') }}" class="btn">Komisi</a></div>
<form method="POST" action="{{ route('app.commissions.assignments.save') }}" class="card">@csrf
  <div class="table-wrap" style="max-height:70vh;overflow:auto"><table class="table">
    <thead><tr><th>Pelanggan</th><th>Alamat</th><th style="width:240px">Kolektor</th></tr></thead><tbody>
    @forelse ($customers as $c)<tr><td>{{ $c->name }} <span class="mono small faint">{{ $c->customer_code }}</span></td><td class="small muted">{{ \Illuminate\Support\Str::limit($c->address, 60) }}</td>
      <td><select name="assign[{{ $c->id }}]" class="input" style="padding:.35rem .5rem"><option value="">—</option>@foreach ($collectors as $u)<option value="{{ $u->id }}" @selected(($map[$c->id] ?? null) === $u->id)>{{ $u->name }}</option>@endforeach</select></td></tr>
    @empty <tr><td colspan="3" class="empty">Belum ada pelanggan.</td></tr> @endforelse
  </tbody></table></div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan penugasan</button></div>
</form>
@endsection
