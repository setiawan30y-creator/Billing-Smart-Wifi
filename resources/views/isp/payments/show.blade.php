@extends('layouts.app')
@section('title', $payment->number)
@section('content')
<div class="page-head">
  <div><h1 class="mono">{{ $payment->number }} <x-badge :status="$payment->status" :label="\App\Models\Payment::STATUSES[$payment->status]"/></h1>
    <p><a href="{{ route('app.customers.show', $payment->customer) }}">{{ $payment->customer->name }}</a> · {{ rupiah($payment->amount) }}</p></div>
  <div class="actions">
    @if ($payment->status === 'verified')<a href="{{ route('app.payments.receipt', $payment) }}" target="_blank" class="btn"><x-icon name="printer"/> Kuitansi</a>@endif
  </div>
</div>
<div class="grid grid-main">
  <div class="stack">
    <div class="card"><div class="card-body"><dl class="kv">
      <dt>Jumlah</dt><dd><strong>{{ rupiah($payment->amount) }}</strong></dd>
      <dt>Metode</dt><dd>{{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }}</dd>
      <dt>Referensi</dt><dd class="mono">{{ $payment->reference ?: '-' }}</dd>
      <dt>Waktu bayar</dt><dd>{{ $payment->paid_at->format('d/m/Y H:i') }}</dd>
      <dt>Dicatat oleh</dt><dd>{{ $payment->receiver?->name ?? 'Sistem / webhook' }} · {{ $payment->created_at->format('d/m/Y H:i') }}</dd>
      @if ($payment->verified_at)<dt>Diverifikasi</dt><dd>{{ $payment->verifier?->name ?? 'Sistem' }} · {{ $payment->verified_at->format('d/m/Y H:i') }}</dd>@endif
      @if ($payment->reversed_at)<dt>Reversal</dt><dd>{{ $payment->reversed_at->format('d/m/Y H:i') }} — {{ $payment->reversal_reason }}</dd>@endif
      @if ($payment->status === 'rejected')<dt>Alasan tolak</dt><dd>{{ $payment->reversal_reason }}</dd>@endif
      @if (\App\Support\Money::isPositive($payment->unallocated_amount))<dt>Belum teralokasi</dt><dd><x-badge status="pending" :label="rupiah($payment->unallocated_amount).' (kelebihan bayar)'"/></dd>@endif
      <dt>Bukti</dt><dd>@if ($payment->proof_path)<a href="{{ route('app.payments.proof', $payment) }}" target="_blank">Lihat bukti</a>@else - @endif</dd>
      @if ($payment->notes)<dt>Catatan</dt><dd>{{ $payment->notes }}</dd>@endif
    </dl></div></div>
    <div class="card"><div class="card-head"><h3>Alokasi ke invoice</h3></div><div class="table-wrap"><table class="table"><tbody>
      @forelse ($payment->allocations as $a)
        <tr><td><a class="mono small" href="{{ route('app.invoices.show', $a->invoice) }}">{{ $a->invoice->number }}</a></td><td class="num">{{ rupiah($a->amount) }}</td><td class="right"><x-badge :status="$a->invoice->status" :label="\App\Models\Invoice::STATUSES[$a->invoice->status]"/></td></tr>
      @empty <tr><td class="empty">{{ $payment->status === 'pending' ? 'Alokasi dibuat saat verifikasi.' : 'Tidak ada alokasi.' }}</td></tr> @endforelse
    </tbody></table></div></div>
  </div>
  <div class="stack">
    @if ($payment->status === 'pending')
      @can('payments.verify')
      <div class="card"><div class="card-head"><h3>Verifikasi</h3></div>
        <form method="POST" action="{{ route('app.payments.verify', $payment) }}" class="card-body stack">@csrf
          <p class="small muted" style="margin:0">Pastikan dana sudah benar-benar diterima. Pilih invoice tujuan (opsional):</p>
          @forelse ($openInvoices as $i)
            <label class="check small"><input type="checkbox" name="invoice_ids[]" value="{{ $i->id }}" @checked(in_array($i->id, $payment->planned_invoice_ids ?? []))> <span class="mono">{{ $i->number }}</span> · sisa {{ rupiah($i->balance()) }}</label>
          @empty <p class="small faint">Tidak ada invoice terbuka.</p> @endforelse
          <button class="btn btn-primary"><x-icon name="check"/> Verifikasi & alokasikan</button>
        </form>
        <form method="POST" action="{{ route('app.payments.reject', $payment) }}" class="card-body" style="border-top:1px solid var(--border);display:flex;gap:.4rem">@csrf
          <input name="reason" class="input" placeholder="Alasan penolakan" required><button class="btn btn-danger">Tolak</button>
        </form>
      </div>
      @endcan
    @elseif ($payment->status === 'verified')
      @can('payments.reverse')
      <div class="card"><div class="card-head"><h3>Reversal</h3></div>
        <form method="POST" action="{{ route('app.payments.reverse', $payment) }}" class="card-body stack">@csrf
          <p class="small muted" style="margin:0">Pembayaran tidak dihapus. Statusnya dibalik dan status invoice dihitung ulang.</p>
          <input name="reason" class="input" placeholder="Alasan reversal" required>
          <button class="btn btn-danger">Reversal pembayaran</button>
        </form></div>
      @endcan
    @endif
  </div>
</div>
@endsection
