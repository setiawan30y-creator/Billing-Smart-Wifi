@extends('layouts.app')
@section('title', 'Invoice')
@section('content')
<div class="page-head"><div><h1>Invoice</h1><p>Tagihan internet pelanggan.</p></div>
  @can('invoices.create')<a href="{{ route('app.invoices.create') }}" class="btn"><x-icon name="plus"/> Invoice manual</a>@endcan</div>
@can('invoices.create')
<div class="card" style="margin-bottom:1rem"><form method="POST" action="{{ route('app.invoices.generate') }}" class="toolbar" style="border:0">@csrf
  <strong>Generate invoice bulanan</strong>
  <input type="month" name="period" class="input" value="{{ $defaultPeriod }}" required>
  <label class="check small"><input type="checkbox" name="notify" value="1" checked> Kirim notifikasi WhatsApp</label>
  <button class="btn btn-primary">Generate</button>
  <span class="small muted">Aman diulang — layanan yang sudah punya invoice periode tsb. dilewati.</span>
</form></div>
@endcan
<div class="card">
  <form class="toolbar" method="GET">
    <input class="input grow" name="q" value="{{ request('q') }}" placeholder="Cari nomor invoice atau pelanggan">
    <select name="status" class="input"><option value="">Semua status</option><option value="overdue" @selected(request('status') === 'overdue')>Overdue</option>@foreach (\App\Models\Invoice::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
    <input type="month" name="period" class="input" value="{{ request('period') }}">
    <button class="btn"><x-icon name="search"/> Filter</button>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Nomor</th><th>Pelanggan</th><th>Periode</th><th>Jatuh tempo</th><th class="num">Total</th><th class="num">Sisa</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($invoices as $i)
      <tr><td><a href="{{ route('app.invoices.show', $i) }}" class="mono small">{{ $i->number }}</a></td>
        <td>{{ $i->customer?->name }}<div class="small faint mono">{{ $i->customer?->customer_code }}</div></td>
        <td>{{ $i->period ?? '-' }}</td><td class="small nowrap">{{ tgl($i->due_date) }}</td>
        <td class="num">{{ rupiah($i->total) }}</td><td class="num">{{ rupiah($i->balance()) }}</td>
        <td><x-badge :status="$i->isOverdue() ? 'failed' : $i->status" :label="$i->isOverdue() ? 'Overdue' : \App\Models\Invoice::STATUSES[$i->status]"/></td></tr>
    @empty <tr><td colspan="7" class="empty">Belum ada invoice.</td></tr> @endforelse
    </tbody></table></div>
  {{ $invoices->links() }}
</div>
@endsection
