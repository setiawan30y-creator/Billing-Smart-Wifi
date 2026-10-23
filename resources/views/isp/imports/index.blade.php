@extends('layouts.app')
@section('title', 'Import Data Pelanggan')
@section('content')
<div class="page-head"><div><h1>Import Data Pelanggan</h1><p>Pindahkan data pelanggan dari Excel/CSV. Data diperiksa dulu sebelum disimpan.</p></div></div>
<div class="grid grid-main" style="margin-bottom:1rem">
  <div class="card">
    <div class="card-head"><h3>1. Unduh template</h3></div>
    <div class="card-body">
      <div class="actions" style="margin-bottom:1rem">
        <a href="{{ route('app.imports.template') }}" class="btn"><x-icon name="download"/> Template Excel (.xlsx)</a>
        <a href="{{ route('app.imports.template', ['format' => 'csv']) }}" class="btn"><x-icon name="download"/> Template CSV</a>
      </div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Kolom</th><th>Keterangan</th><th>Wajib</th></tr></thead>
        <tbody>@foreach ($columns as $k => [$label, $req, $ex])<tr><td class="mono small">{{ $k }}</td><td class="small">{{ $label }}</td><td>@if ($req)<x-badge status="failed" label="wajib"/>@endif</td></tr>@endforeach</tbody>
      </table></div>
      <p class="small muted" style="margin-top:.75rem">Kode paket & kode ODP harus sudah dibuat di menu Paket Layanan dan Titik ODP. NIK/KTP tidak diimport demi keamanan data — isi melalui form pelanggan. Saldo awal/tunggakan lama tidak diimport otomatis.</p>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h3>2. Unggah file</h3></div>
    <form method="POST" action="{{ route('app.imports.store') }}" enctype="multipart/form-data" class="card-body stack">
      @csrf
      <x-field name="file" label="File .xlsx / .csv (maks 5 MB, 5.000 baris)" type="file" accept=".xlsx,.csv" required/>
      <button class="btn btn-primary"><x-icon name="upload"/> Unggah & periksa</button>
      <p class="small muted" style="margin:0">Tahap ini hanya memeriksa data. Pelanggan baru dibuat setelah Anda menekan tombol <strong>Import</strong> di halaman hasil.</p>
    </form>
  </div>
</div>
<div class="card"><div class="card-head"><h3>Riwayat import</h3></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Waktu</th><th>File</th><th class="num">Baris</th><th class="num">Valid</th><th class="num">Error</th><th class="num">Diimport</th><th>Status</th><th>Oleh</th></tr></thead>
    <tbody>
    @forelse ($batches as $b)
      <tr><td class="small nowrap"><a href="{{ route('app.imports.show', $b) }}">{{ $b->created_at->format('d/m/Y H:i') }}</a></td><td class="small">{{ $b->original_name }}</td>
        <td class="num">{{ $b->total_rows }}</td><td class="num">{{ $b->valid_rows }}</td><td class="num">{{ $b->error_rows }}</td><td class="num">{{ $b->imported_rows }}</td>
        <td><x-badge :status="['validated' => 'pending', 'processing' => 'running', 'completed' => 'done', 'failed' => 'failed', 'cancelled' => 'void'][$b->status]" :label="\App\Models\ImportBatch::STATUSES[$b->status]"/></td>
        <td class="small">{{ $b->creator?->name }}</td></tr>
    @empty <tr><td colspan="8" class="empty">Belum ada import.</td></tr> @endforelse
    </tbody></table></div>
  {{ $batches->links() }}
</div>
@endsection
