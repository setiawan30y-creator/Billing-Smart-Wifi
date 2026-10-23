@extends('layouts.app')
@section('title', 'Backup Data')
@section('content')
<div class="page-head"><div><h1>Backup Data</h1><p>Backup terenkripsi seluruh data operasional ISP Anda. Backup otomatis berjalan tiap minggu.</p></div>
  <form method="POST" action="{{ route('app.backups.store') }}">@csrf<button class="btn btn-primary"><x-icon name="database"/> Buat backup sekarang</button></form></div>
<div class="alert alert-info">File backup dienkripsi dengan kunci aplikasi (APP_KEY) — simpan APP_KEY dengan aman. Berkas unggahan (foto KTP, foto rumah, bukti bayar) tidak termasuk dalam file ini. Restore ke data produksi hanya dapat dilakukan oleh pengelola platform setelah uji restore lulus.</div>
<div class="grid grid-main">
  <div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Waktu</th><th>Jenis</th><th>Status</th><th class="num">Ukuran</th><th>Isi</th><th></th></tr></thead><tbody>
    @forelse ($jobs as $j)<tr><td class="small nowrap">{{ $j->started_at->format('d/m/Y H:i') }}<div class="faint">{{ $j->creator?->name ?? 'Terjadwal' }}</div></td>
      <td class="small">{{ ['manual' => 'Manual', 'scheduled' => 'Terjadwal', 'pre_restore' => 'Pengaman sebelum restore'][$j->trigger] }}</td>
      <td><x-badge :status="['running' => 'running', 'completed' => 'done', 'failed' => 'failed'][$j->status]" :label="$j->status"/>@if ($j->error)<div class="small" style="color:var(--danger)">{{ \Illuminate\Support\Str::limit($j->error, 80) }}</div>@endif</td>
      <td class="num small">{{ $j->artifact ? number_format($j->artifact->size_bytes / 1024, 0, ',', '.').' KB' : '-' }}</td>
      <td class="small">{{ $j->artifact ? array_sum($j->artifact->table_counts).' baris' : '-' }}@if ($j->artifact?->expires_at)<div class="faint">disimpan s/d {{ $j->artifact->expires_at->format('d/m/Y') }}</div>@endif</td>
      <td class="right nowrap">@if ($j->artifact)<form method="POST" action="{{ route('app.backups.test', $j->artifact) }}" style="display:inline">@csrf<button class="btn btn-sm">Uji restore</button></form> <a class="btn btn-sm btn-ghost" href="{{ route('app.backups.download', $j->artifact) }}">Unduh</a>@endif</td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada backup.</td></tr> @endforelse
  </tbody></table></div>{{ $jobs->links() }}</div>
  <div class="stack">
    <div class="card"><div class="card-head"><h3>Hasil uji restore</h3></div><div class="card-body">
      @forelse ($tests as $t)<div class="small" style="margin-bottom:.6rem"><x-badge :status="['passed' => 'done', 'failed' => 'failed', 'running' => 'running', 'completed' => 'done'][$t->status]" :label="($t->mode === 'test' ? 'Uji ' : 'Restore ').$t->status"/> {{ $t->started_at->format('d/m H:i') }}
        <div class="faint">{{ $t->report['error'] ?? implode(' · ', $t->report['checks'] ?? []) }}</div></div>
      @empty <p class="small muted">Belum ada uji restore. Backup yang belum pernah diuji belum terbukti bisa dipulihkan.</p> @endforelse
    </div></div>
    <div class="card"><div class="card-head"><h3>Log backup</h3></div><div class="card-body">
      @foreach ($logs as $l)<div class="small"><span class="mono">{{ $l->action }}</span> · {{ $l->user?->name ?? 'Sistem' }} · <span class="faint">{{ $l->created_at->format('d/m H:i') }}</span></div>@endforeach
    </div></div>
  </div>
</div>
@endsection
