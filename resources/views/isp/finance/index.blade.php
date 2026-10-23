@extends('layouts.app')
@section('title', 'Audit Keuangan')
@section('content')
<div class="page-head"><div><h1>Audit Keuangan</h1><p>Ringkasan periode, penyesuaian (maker-checker), dan penutupan buku.</p></div>
  <form method="GET" style="display:flex;gap:.4rem"><input type="month" name="period" class="input" value="{{ $period }}"><button class="btn">Lihat</button></form></div>
@if ($closed)<div class="alert alert-info">Periode {{ $period }} sudah ditutup — transaksi bertanggal di periode ini tidak dapat diubah.</div>@endif
<div class="grid grid-4" style="margin-bottom:1rem">
  <div class="card stat"><div class="stat-label">Ditagihkan ({{ $summary['invoice_count'] }} invoice)</div><div class="stat-value">{{ rupiah($summary['invoiced']) }}</div></div>
  <div class="card stat"><div class="stat-label">Diterima (terverifikasi)</div><div class="stat-value" style="color:var(--success)">{{ rupiah($summary['collected']) }}</div><div class="stat-sub">@foreach ($summary['by_method'] as $m => $v){{ \App\Models\Payment::METHODS[$m] ?? $m }} {{ rupiah($v) }}@if (! $loop->last) · @endif @endforeach</div></div>
  <div class="card stat"><div class="stat-label">Penyesuaian disetujui</div><div class="stat-value">{{ rupiah($summary['adjusted']) }}</div></div>
  <div class="card stat"><div class="stat-label">Sisa piutang invoice periode ini</div><div class="stat-value" style="color:var(--danger)">{{ rupiah($summary['outstanding']) }}</div></div>
</div>
<div class="grid grid-main">
  <div class="card"><div class="card-head"><h3>Penyesuaian keuangan</h3></div><div class="table-wrap"><table class="table">
    <thead><tr><th>Nomor</th><th>Invoice / pelanggan</th><th class="num">Nominal</th><th>Alasan</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse ($adjustments as $a)<tr><td class="mono small">{{ $a->number }}<div class="faint">{{ \App\Models\FinancialAdjustment::TYPES[$a->type] }}</div></td>
      <td class="small">@if ($a->invoice)<a href="{{ route('app.invoices.show', $a->invoice) }}">{{ $a->invoice->number }}</a>@endif<div>{{ $a->customer?->name }}</div></td>
      <td class="num">{{ rupiah($a->amount) }}</td><td class="small">{{ $a->reason }}<div class="faint">oleh {{ $a->creator?->name }}{{ $a->approver ? ' · ditinjau '.$a->approver->name : '' }}{{ $a->review_note ? ': '.$a->review_note : '' }}</div></td>
      <td><x-badge :status="['pending' => 'pending', 'approved' => 'done', 'rejected' => 'void'][$a->status]" :label="['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$a->status]"/></td>
      <td class="right">@if ($a->status === 'pending')@can('finance.adjustments.approve')<form method="POST" action="{{ route('app.finance.adjustments.review', $a) }}" style="display:flex;gap:.3rem;justify-content:flex-end">@csrf<input name="note" class="input" style="padding:.3rem .5rem;max-width:140px" placeholder="Catatan"><button name="decision" value="approve" class="btn btn-sm btn-primary">Setujui</button><button name="decision" value="reject" class="btn btn-sm">Tolak</button></form>@endcan @endif</td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada penyesuaian. Ajukan dari halaman detail invoice.</td></tr> @endforelse
  </tbody></table></div>{{ $adjustments->links() }}</div>
  <div class="stack">
    @can('finance.periods.close')
    <div class="card"><div class="card-head"><h3>Tutup periode</h3></div>
      <form method="POST" action="{{ route('app.finance.periods.close') }}" class="card-body stack">@csrf
        <x-field name="period" label="Periode" type="month" :value="now()->subMonth()->format('Y-m')" required/>
        <x-field name="note" label="Catatan"/>
        <button class="btn">Tutup periode</button>
        <p class="small muted" style="margin:0">Setelah ditutup: invoice/pembayaran bertanggal di periode itu tidak bisa dibuat, di-void, atau di-reversal. Koreksi lewat penyesuaian.</p>
      </form></div>
    @endcan
    <div class="card"><div class="card-head"><h3>Status periode</h3></div><div class="card-body">
      @forelse ($periods as $p)<div style="display:flex;justify-content:space-between;gap:.5rem;margin-bottom:.5rem"><div class="small"><strong>{{ $p->period }}</strong> <x-badge :status="$p->status === 'closed' ? 'done' : 'pending'" :label="$p->status === 'closed' ? 'Ditutup' : 'Dibuka ulang'"/><div class="faint">{{ $p->closer?->name }} · {{ $p->closed_at?->format('d/m/Y') }}{{ $p->note ? ' · '.$p->note : '' }}</div></div>
        @can('finance.periods.close')@if ($p->status === 'closed')<form method="POST" action="{{ route('app.finance.periods.reopen', $p) }}" style="display:flex;gap:.3rem">@csrf<input name="reason" class="input" style="padding:.3rem .5rem;max-width:120px" placeholder="Alasan" required><button class="btn btn-sm">Buka</button></form>@endif @endcan</div>
      @empty <p class="small muted">Belum ada periode ditutup.</p> @endforelse
    </div></div>
  </div>
</div>
@endsection
