@extends('portal.layout')
@section('title', 'Riwayat pembayaran')
@section('content')
<h1>Riwayat pembayaran</h1>
<div class="card">
  @forelse ($payments as $p)
    <div class="list-item">
      <div><strong>{{ rupiah($p->amount) }}</strong><div class="small muted">{{ $p->paid_at->format('d/m/Y H:i') }} · {{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}</div>
        <div class="small faint">{{ $p->allocations->map(fn ($a) => $a->invoice->period ?? $a->invoice->number)->implode(', ') }}</div></div>
      <div class="right"><x-badge :status="$p->status" :label="['pending' => 'Menunggu verifikasi', 'verified' => 'Diterima', 'rejected' => 'Ditolak', 'reversed' => 'Dibatalkan'][$p->status]"/>
        @if ($p->status === 'verified')<div style="margin-top:.35rem"><a class="btn btn-sm" target="_blank" href="{{ route('portal.payments.receipt', [$tenant->code, $p->id]) }}">Kuitansi</a></div>@endif
        @if ($p->status === 'rejected' && $p->reversal_reason)<div class="small" style="color:var(--danger)">{{ $p->reversal_reason }}</div>@endif</div>
    </div>
  @empty <div class="card-body muted">Belum ada pembayaran.</div> @endforelse
  {{ $payments->links() }}
</div>
@endsection
