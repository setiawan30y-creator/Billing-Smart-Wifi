@extends('layouts.app')
@section('title', 'Paket Layanan')
@section('content')
<div class="page-head"><div><h1>Paket Layanan Internet</h1><p>Paket yang dijual ISP Anda kepada pelanggan.</p></div><a href="{{ route('app.packages.create') }}" class="btn btn-primary"><x-icon name="plus"/> Paket baru</a></div>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Paket</th><th>Batas kecepatan</th><th class="num">Harga/bulan</th><th class="num">Layanan aktif</th><th>Status</th><th></th></tr></thead>
  <tbody>
  @forelse ($packages as $p)
    <tr><td><strong>{{ $p->name }}</strong><div class="small faint mono">{{ $p->code }}</div></td><td>{{ $p->speedLabel() }}</td><td class="num">{{ rupiah($p->price) }}</td><td class="num">{{ $p->services_count }}</td>
      <td>@if ($p->is_active)<x-badge status="active" label="Aktif"/>@else<x-badge status="void" label="Nonaktif"/>@endif</td>
      <td class="right"><a class="btn btn-sm" href="{{ route('app.packages.edit', $p) }}">Ubah</a></td></tr>
  @empty <tr><td colspan="6" class="empty">Belum ada paket. Tambahkan paket sebelum mendaftarkan layanan pelanggan.</td></tr> @endforelse
  </tbody></table></div></div>
<p class="small faint" style="margin-top:.75rem">Batas kecepatan paket bukan bukti kecepatan aktual pelanggan.</p>
@endsection
