@extends('layouts.app')
@section('title', 'Hasil import')
@section('content')
<div class="page-head">
  <div><h1>Hasil import <x-badge :status="['validated' => 'pending', 'processing' => 'running', 'completed' => 'done', 'failed' => 'failed', 'cancelled' => 'void'][$batch->status]" :label="\App\Models\ImportBatch::STATUSES[$batch->status]"/></h1><p>{{ $batch->original_name }} · diunggah {{ $batch->created_at->format('d/m/Y H:i') }}</p></div>
  <div class="actions">
    <a href="{{ route('app.imports.index') }}" class="btn">Kembali</a>
    @if ($batch->error_rows || $batch->failed_rows)<a href="{{ route('app.imports.errors', $batch) }}" class="btn"><x-icon name="download"/> Unduh baris error</a>@endif
  </div>
</div>
<div class="grid grid-4" style="margin-bottom:1rem">
  <div class="card stat"><div class="stat-label">Total baris</div><div class="stat-value">{{ $batch->total_rows }}</div></div>
  <div class="card stat"><div class="stat-label">Valid</div><div class="stat-value" style="color:var(--success)">{{ $batch->valid_rows }}</div></div>
  <div class="card stat"><div class="stat-label">Error</div><div class="stat-value" style="color:var(--danger)">{{ $batch->error_rows }}</div></div>
  <div class="card stat"><div class="stat-label">Berhasil diimport</div><div class="stat-value">{{ $batch->imported_rows }}</div>@if ($batch->failed_rows)<div class="stat-sub" style="color:var(--danger)">{{ $batch->failed_rows }} gagal saat disimpan</div>@endif</div>
</div>

@if ($batch->status === 'validated')
  <div class="card" style="margin-bottom:1rem"><div class="card-body" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;justify-content:space-between">
    <div>
      @if ($batch->error_rows)
        <strong>{{ $batch->valid_rows }} baris valid akan diimport. {{ $batch->error_rows }} baris error TIDAK akan diimport.</strong>
        <div class="small muted">Unduh baris error, perbaiki, lalu unggah sebagai file baru.</div>
      @else
        <strong>Semua {{ $batch->valid_rows }} baris valid dan siap diimport.</strong>
      @endif
    </div>
    <div class="actions">
      <form method="POST" action="{{ route('app.imports.cancel', $batch) }}">@csrf<button class="btn">Batalkan</button></form>
      @if ($batch->valid_rows)<form method="POST" action="{{ route('app.imports.execute', $batch) }}">@csrf<button class="btn btn-primary">Import {{ $batch->valid_rows }} pelanggan</button></form>@endif
    </div>
  </div></div>
@elseif ($batch->status === 'processing')
  <div class="alert alert-info">Import sedang diproses. Muat ulang halaman ini beberapa saat lagi.</div>
@elseif ($batch->status === 'completed')
  <div class="alert alert-success">Import selesai {{ $batch->completed_at?->format('d/m/Y H:i') }}: {{ $batch->imported_rows }} pelanggan dibuat{{ $batch->failed_rows ? ', '.$batch->failed_rows.' gagal saat disimpan' : '' }}{{ $batch->error_rows ? ', '.$batch->error_rows.' baris error tidak diimport' : '' }}.</div>
@elseif ($batch->status === 'failed')
  <div class="alert alert-danger">Import gagal: {{ $batch->message }}</div>
@endif

<div class="card">
  <div class="toolbar">
    @foreach (['' => 'Semua', 'valid' => 'Valid', 'error' => 'Error', 'warning' => 'Peringatan', 'imported' => 'Diimport', 'failed' => 'Gagal'] as $k => $l)
      <a href="{{ route('app.imports.show', [$batch, 'filter' => $k ?: null]) }}" class="badge badge-{{ ($filter ?? '') === $k ? 'primary' : 'muted' }}">{{ $l }}</a>
    @endforeach
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Baris</th><th>Nama</th><th>WhatsApp</th><th>Paket / ODP</th><th>Status</th><th>Keterangan</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr>
        <td class="mono">{{ $r->row_number }}</td>
        <td>@if ($r->customer_id)<a href="{{ route('app.customers.show', $r->customer_id) }}">{{ $r->data['nama'] ?? '' }}</a>@else{{ $r->data['nama'] ?? '' }}@endif<div class="small faint mono">{{ $r->data['kode_pelanggan'] ?? '' }}</div></td>
        <td class="small mono">{{ $r->data['no_whatsapp'] ?? '' }}</td>
        <td class="small">{{ $r->data['kode_paket'] ?? '' }}@if (! empty($r->data['kode_odp']))<div class="faint">{{ $r->data['kode_odp'] }} · port {{ $r->data['port_odp'] ?? '' }}</div>@endif</td>
        <td><x-badge :status="['valid' => 'active', 'error' => 'failed', 'imported' => 'done', 'failed' => 'failed'][$r->status]" :label="['valid' => 'Valid', 'error' => 'Error', 'imported' => 'Diimport', 'failed' => 'Gagal'][$r->status]"/></td>
        <td class="small" style="max-width:380px">
          @foreach ($r->errors ?? [] as $e)<div style="color:var(--danger)">• {{ $e }}</div>@endforeach
          @foreach ($r->warnings ?? [] as $w)<div style="color:var(--warning)">• {{ $w }}</div>@endforeach
        </td>
      </tr>
    @empty <tr><td colspan="6" class="empty">Tidak ada baris.</td></tr> @endforelse
    </tbody></table></div>
  {{ $rows->links() }}
</div>
@endsection
