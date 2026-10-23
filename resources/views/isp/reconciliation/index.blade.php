@extends('layouts.app')
@section('title', 'Rekonsiliasi')
@section('content')
<div class="page-head"><div><h1>Rekonsiliasi pembayaran</h1><p>Cocokkan mutasi rekening bank dengan pembayaran yang tercatat.</p></div></div>
@if ($pendingTransfers)<div class="alert alert-info">{{ $pendingTransfers }} pembayaran transfer menunggu verifikasi. Unggah mutasi bank untuk mencocokkannya.</div>@endif
<div class="grid grid-main" style="margin-bottom:1rem">
  <div class="card"><div class="card-head"><h3>Riwayat rekonsiliasi</h3></div><div class="table-wrap"><table class="table">
    <thead><tr><th>Nomor</th><th>File</th><th class="num">Baris</th><th class="num">Cocok</th><th class="num">Belum</th><th class="num">Total masuk</th><th>Status</th></tr></thead><tbody>
    @forelse ($batches as $b)<tr><td><a class="mono" href="{{ route('app.reconciliation.show', $b) }}">{{ $b->number }}</a><div class="small faint">{{ $b->created_at->format('d/m/Y H:i') }}</div></td><td class="small">{{ $b->original_name }}</td>
      <td class="num">{{ $b->total_items }}</td><td class="num">{{ $b->matched_count }}</td><td class="num">{{ $b->unmatched_count }}</td><td class="num">{{ rupiah($b->total_amount) }}</td>
      <td><x-badge :status="$b->status === 'completed' ? 'done' : 'pending'" :label="$b->status === 'completed' ? 'Selesai' : 'Terbuka'"/></td></tr>
    @empty <tr><td colspan="7" class="empty">Belum ada rekonsiliasi.</td></tr> @endforelse
  </tbody></table></div>{{ $batches->links() }}</div>
  <div class="card"><div class="card-head"><h3>Unggah mutasi bank</h3></div>
    <form method="POST" action="{{ route('app.reconciliation.store') }}" enctype="multipart/form-data" class="card-body stack">@csrf
      <x-field name="file" label="File .xlsx / .csv" type="file" accept=".xlsx,.csv" required hint="Kolom: tanggal, keterangan, jumlah, referensi. Hanya dana masuk (jumlah > 0) yang diproses."/>
      <a href="{{ route('app.reconciliation.template') }}" class="small"><x-icon name="download" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;vertical-align:-2px"/> Unduh template mutasi</a>
      <button class="btn btn-primary">Unggah & cocokkan</button>
    </form></div>
</div>
@endsection
