@extends('layouts.app')
@section('title', $batch->number)
@section('content')
@php $open = $batch->status === 'open'; @endphp
<div class="page-head"><div><h1 class="mono">{{ $batch->number }} <x-badge :status="$open ? 'pending' : 'done'" :label="$open ? 'Terbuka' : 'Selesai'"/></h1><p>{{ $batch->original_name }} · {{ $batch->total_items }} baris · {{ rupiah($batch->total_amount) }}</p></div>
  <div class="actions"><a href="{{ route('app.reconciliation.index') }}" class="btn">Kembali</a>
    @if ($open)
      @can('payments.verify')<form method="POST" action="{{ route('app.reconciliation.verify', $batch) }}">@csrf<button class="btn">Verifikasi pembayaran pending yang cocok</button></form>@endcan
      <form method="POST" action="{{ route('app.reconciliation.complete', $batch) }}">@csrf<button class="btn btn-primary">Tutup rekonsiliasi</button></form>
    @endif</div></div>
<div class="toolbar card" style="margin-bottom:1rem">@foreach (['' => 'Semua', 'unmatched' => 'Belum cocok', 'matched' => 'Cocok', 'ignored' => 'Diabaikan'] as $k => $l)<a href="{{ route('app.reconciliation.show', [$batch, 'status' => $k ?: null]) }}" class="badge badge-{{ request('status', '') === $k ? 'primary' : 'muted' }}">{{ $l }}</a>@endforeach</div>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Tanggal</th><th>Keterangan</th><th class="num">Jumlah</th><th>Status</th><th>Pembayaran / tindakan</th></tr></thead><tbody>
  @foreach ($items as $it)
    <tr><td class="small nowrap">{{ $it->txn_date ? tgl($it->txn_date, 'd/m/Y') : '?' }}</td><td class="small" style="max-width:320px">{{ $it->description }}@if ($it->reference)<div class="mono faint">{{ $it->reference }}</div>@endif</td>
      <td class="num"><strong>{{ rupiah($it->amount) }}</strong></td>
      <td><x-badge :status="['matched' => 'done', 'unmatched' => 'pending', 'ignored' => 'void'][$it->status]" :label="['matched' => 'Cocok', 'unmatched' => 'Belum cocok', 'ignored' => 'Diabaikan'][$it->status]"/>@if ($it->match_type)<div class="small faint">{{ $it->match_type }}{{ $it->note ? ' · '.$it->note : '' }}</div>@endif</td>
      <td class="small">
        @if ($it->payment)
          <a class="mono" href="{{ route('app.payments.show', $it->payment) }}">{{ $it->payment->number }}</a> · {{ $it->payment->customer?->name }} <x-badge :status="$it->payment->status" :label="\App\Models\Payment::STATUSES[$it->payment->status]"/>
          @if ($open)<form method="POST" action="{{ route('app.reconciliation.resolve', [$batch, $it]) }}" style="display:inline">@csrf<input type="hidden" name="action" value="unmatch"><button class="btn btn-sm btn-ghost">Batalkan cocok</button></form>@endif
        @elseif ($open && $it->status === 'unmatched')
          <div class="stack" style="gap:.35rem">
            @php $cands = $candidates[(string) $it->amount] ?? collect(); @endphp
            @if ($cands->count())<form method="POST" action="{{ route('app.reconciliation.resolve', [$batch, $it]) }}" style="display:flex;gap:.3rem">@csrf<input type="hidden" name="action" value="match">
              <select name="payment_id" class="input" style="padding:.3rem .5rem">@foreach ($cands as $p)<option value="{{ $p->id }}">{{ $p->number }} · {{ $p->customer?->name }} · {{ $p->paid_at->format('d/m') }} · {{ $p->status }}</option>@endforeach</select><button class="btn btn-sm">Cocokkan</button></form>@endif
            <form method="POST" action="{{ route('app.reconciliation.resolve', [$batch, $it]) }}" style="display:flex;gap:.3rem">@csrf<input type="hidden" name="action" value="create">
              <select name="customer_id" class="input" style="padding:.3rem .5rem" required><option value="">Buat pembayaran untuk…</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->customer_code }})</option>@endforeach</select><button class="btn btn-sm">Buat</button></form>
            <form method="POST" action="{{ route('app.reconciliation.resolve', [$batch, $it]) }}" style="display:flex;gap:.3rem">@csrf<input type="hidden" name="action" value="ignore"><input name="note" class="input" style="padding:.3rem .5rem" placeholder="Alasan diabaikan"><button class="btn btn-sm btn-ghost">Abaikan</button></form>
          </div>
        @else <span class="faint">{{ $it->note }}</span> @endif
      </td></tr>
  @endforeach
</tbody></table></div></div>
@endsection
