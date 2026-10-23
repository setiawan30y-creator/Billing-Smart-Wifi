@extends('layouts.app')
@section('title', 'Paket SaaS')
@section('content')
<div class="page-head"><div><h1>Paket SaaS</h1><p>Katalog resmi paket sewa platform. Perubahan tidak memengaruhi langganan berjalan.</p></div>
  <a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><x-icon name="plus"/> Paket baru</a></div>
<div class="alert alert-warning">Harga dengan label <strong>usulan</strong> belum disahkan. Invoice SaaS dari paket tersebut dibuat sebagai <strong>SIMULASI</strong> dan tidak dapat ditagihkan.</div>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Paket</th><th class="num">Harga/bulan</th><th class="num">Client</th><th class="num">MikroTik</th><th class="num">Overage</th><th>Status harga</th><th class="num">Langganan</th><th></th></tr></thead>
  <tbody>
  @foreach ($plans as $p)
    <tr>
      <td><strong>{{ $p->name }}</strong> @unless ($p->is_active)<x-badge status="void" label="nonaktif"/>@endunless<div class="small faint mono">{{ $p->code }}</div></td>
      <td class="num">{{ $p->price_monthly !== null ? rupiah($p->price_monthly) : 'Custom' }}</td>
      <td class="num">{{ $p->client_quota ? number_format($p->client_quota, 0, ',', '.') : 'Kontrak' }}</td>
      <td class="num">{{ $p->router_quota ?? 'Kontrak' }}</td>
      <td class="num small">{{ rupiah($p->overage_client_price) }}/client<br>{{ rupiah($p->overage_router_price) }}/router</td>
      <td>@if ($p->is_price_approved)<x-badge status="active" label="disahkan"/>@else<x-badge status="pending" label="usulan"/>@endif</td>
      <td class="num">{{ $p->subscriptions_count }}</td>
      <td class="right"><a class="btn btn-sm" href="{{ route('admin.plans.edit', $p) }}">Ubah</a></td>
    </tr>
  @endforeach
  </tbody></table></div></div>
@endsection
