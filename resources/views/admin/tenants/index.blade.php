@extends('layouts.app')
@section('title', 'Manajemen Supplier ISP')
@section('content')
<div class="page-head">
  <div><h1>Manajemen Supplier ISP</h1><p>Perusahaan ISP yang menyewa platform ALMARA.</p></div>
  @can('tenants.create')<a href="{{ route('admin.tenants.create') }}" class="btn btn-primary"><x-icon name="plus"/> Tambah ISP</a>@endcan
</div>
<div class="actions" style="margin-bottom:1rem">
  @foreach (\App\Models\Tenant::STATUSES as $k => $label)
    <a href="{{ route('admin.tenants.index', ['status' => $k]) }}" class="badge badge-{{ request('status') === $k ? 'primary' : 'muted' }}">{{ $label }}: {{ $counts[$k] ?? 0 }}</a>
  @endforeach
</div>
<div class="card">
  <form class="toolbar" method="GET">
    <input class="input grow" name="q" value="{{ request('q') }}" placeholder="Cari nama ISP, kode, email, atau kontak">
    <select name="status" class="input"><option value="">Semua status</option>@foreach (\App\Models\Tenant::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
    <select name="plan" class="input"><option value="">Semua paket</option>@foreach ($plans as $p)<option value="{{ $p->id }}" @selected(request('plan') == $p->id)>{{ $p->name }}</option>@endforeach</select>
    <label class="check small"><input type="checkbox" name="expiring" value="1" @checked(request('expiring'))> Berakhir ≤ 14 hari</label>
    <button class="btn"><x-icon name="search"/> Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>ISP</th><th>PIC / Kontak</th><th>Paket</th><th>Masa langganan</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse ($tenants as $t)
      @php $s = $t->currentSubscription; @endphp
      <tr>
        <td><a href="{{ route('admin.tenants.show', $t) }}"><strong>{{ $t->name }}</strong></a><div class="small faint mono">{{ $t->code }}</div></td>
        <td>{{ $t->profile?->pic_name }}<div class="small muted">{{ $t->profile?->whatsapp }} · {{ $t->profile?->email }}</div></td>
        <td>{{ $s?->plan?->name ?? '-' }}<div class="small faint">{{ $s ? ($s->period === 'yearly' ? 'Tahunan' : 'Bulanan') : '' }}</div></td>
        <td class="small nowrap">{{ $s ? tgl($s->starts_at).' – '.tgl($s->ends_at) : '-' }}</td>
        <td><x-badge :status="$t->status" :label="$t->statusLabel()"/></td>
        <td class="right"><a href="{{ route('admin.tenants.show', $t) }}" class="btn btn-sm">Detail</a></td>
      </tr>
    @empty
      <tr><td colspan="6" class="empty">Tidak ada data.</td></tr>
    @endforelse
    </tbody></table></div>
  {{ $tenants->links() }}
</div>
@endsection
