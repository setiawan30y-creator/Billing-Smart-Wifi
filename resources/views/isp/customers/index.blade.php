@extends('layouts.app')
@section('title', 'Pelanggan')
@section('content')
<div class="page-head"><div><h1>Pelanggan</h1><p>Data pelanggan internet dan layanannya.</p></div>
  @can('customers.create')<a href="{{ route('app.customers.create') }}" class="btn btn-primary"><x-icon name="plus"/> Pelanggan baru</a>@endcan</div>
<div class="card">
  <form class="toolbar" method="GET">
    <input class="input grow" name="q" value="{{ request('q') }}" placeholder="Cari nama, ID pelanggan, nomor, atau alamat">
    <select name="status" class="input"><option value="">Semua status</option>@foreach (\App\Models\Customer::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
    <select name="package" class="input"><option value="">Semua paket</option>@foreach ($packages as $p)<option value="{{ $p->id }}" @selected(request('package') == $p->id)>{{ $p->name }}</option>@endforeach</select>
    <button class="btn"><x-icon name="search"/> Cari</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Pelanggan</th><th>Kontak</th><th>Layanan</th><th>NIK</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($customers as $c)
      <tr>
        <td><a href="{{ route('app.customers.show', $c) }}"><strong>{{ $c->name }}</strong></a><div class="small faint mono">{{ $c->customer_code }}</div></td>
        <td class="small">{{ $c->phone }}<div class="muted">{{ \Illuminate\Support\Str::limit($c->address, 50) }}</div></td>
        <td class="small">@forelse ($c->services as $s)<div>{{ $s->package->name }} <x-badge :status="$s->status" :label="\App\Models\CustomerService::STATUSES[$s->status]"/></div>@empty<span class="faint">-</span>@endforelse</td>
        <td class="mono small">{{ $c->maskedNik() }}</td>
        <td><x-badge :status="$c->status" :label="\App\Models\Customer::STATUSES[$c->status]"/></td>
      </tr>
    @empty <tr><td colspan="5" class="empty">Belum ada pelanggan.</td></tr> @endforelse
    </tbody></table></div>
  {{ $customers->links() }}
</div>
@endsection
