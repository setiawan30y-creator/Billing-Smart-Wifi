@extends('portal.layout')
@section('title', 'Tagihan')
@section('content')
<h1>Tagihan</h1>
<div class="card">
  @forelse ($invoices as $i)
    <div class="list-item">
      <div><strong>{{ $i->period ? 'Periode '.$i->period : $i->number }}</strong><div class="small muted mono">{{ $i->number }}</div><div class="small muted">Jatuh tempo {{ tgl($i->due_date) }}</div></div>
      <div class="right"><strong>{{ rupiah($i->total) }}</strong>
        <div><x-badge :status="$i->isOverdue() ? 'failed' : $i->status" :label="$i->isOverdue() ? 'Lewat jatuh tempo' : \App\Models\Invoice::STATUSES[$i->status]"/></div>
        <div class="actions" style="justify-content:flex-end;margin-top:.35rem">
          <a class="btn btn-sm" href="{{ route('portal.invoices.show', [$tenant->code, $i->id]) }}" target="_blank">Lihat</a>
          @if (in_array($i->status, ['unpaid', 'partial']))<a class="btn btn-sm btn-primary" href="{{ route('portal.invoices.pay', [$tenant->code, $i->id]) }}">Bayar</a>@endif
        </div></div>
    </div>
  @empty <div class="card-body muted">Belum ada tagihan.</div> @endforelse
  {{ $invoices->links() }}
</div>
@endsection
