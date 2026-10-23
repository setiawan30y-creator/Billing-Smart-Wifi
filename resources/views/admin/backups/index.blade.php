@extends('layouts.app')
@section('title', 'Backup & Restore')
@section('content')
<div class="page-head"><div><h1>Backup & Restore Tenant</h1><p>Restore memerlukan uji restore yang lulus, konfirmasi kode ISP, alasan, dan otomatis membuat backup pengaman.</p></div>
  <form method="POST" action="{{ route('admin.backups.run') }}" style="display:flex;gap:.4rem">@csrf<select name="tenant_id" class="input" required><option value="">Backup ISP…</option>@foreach ($tenants as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select><button class="btn btn-primary">Backup</button></form></div>
<div class="card" style="margin-bottom:1rem">
  <form class="toolbar" method="GET"><select name="tenant" class="input" onchange="this.form.submit()"><option value="">Semua ISP</option>@foreach ($tenants as $t)<option value="{{ $t->id }}" @selected(request('tenant') == $t->id)>{{ $t->name }}</option>@endforeach</select></form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Waktu</th><th>ISP</th><th>Jenis</th><th>Status</th><th>Isi</th><th>Uji / restore</th></tr></thead><tbody>
    @forelse ($jobs as $j)<tr><td class="small nowrap">{{ $j->started_at->format('d/m/Y H:i') }}</td><td>{{ $j->tenant?->name }} <span class="mono small faint">{{ $j->tenant?->code }}</span></td><td class="small">{{ $j->trigger }}</td>
      <td><x-badge :status="['running' => 'running', 'completed' => 'done', 'failed' => 'failed'][$j->status]" :label="$j->status"/></td>
      <td class="small">{{ $j->artifact ? array_sum($j->artifact->table_counts).' baris · '.number_format($j->artifact->size_bytes / 1024, 0, ',', '.').' KB' : '-' }}</td>
      <td>@if ($j->artifact)
        @php $passed = $j->artifact->restores()->where('mode', 'test')->where('status', 'passed')->exists(); @endphp
        <div class="actions"><form method="POST" action="{{ route('admin.backups.test', $j->artifact) }}">@csrf<button class="btn btn-sm">{{ $passed ? 'Uji ulang' : 'Uji restore' }}</button></form>
        @if ($passed)<details><summary class="btn btn-sm btn-danger">Restore…</summary>
          <form method="POST" action="{{ route('admin.backups.restore', $j->artifact) }}" class="card card-body stack" style="position:absolute;z-index:10;width:300px;margin-top:.3rem">@csrf
            <p class="small" style="margin:0">Data operasional <strong>{{ $j->tenant?->name }}</strong> akan ditimpa dengan isi backup {{ $j->started_at->format('d/m/Y H:i') }}. Audit log tidak dihapus.</p>
            <input name="confirm_code" class="input" placeholder="Ketik {{ $j->tenant?->code }}" required>
            <input name="reason" class="input" placeholder="Alasan restore" required>
            <button class="btn btn-danger">Restore sekarang</button></form></details>@endif</div>
      @endif</td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada backup.</td></tr> @endforelse
  </tbody></table></div>{{ $jobs->links() }}</div>
<div class="card"><div class="card-head"><h3>Riwayat uji & restore</h3></div><div class="table-wrap"><table class="table"><tbody>
  @forelse ($restores as $r)<tr><td class="small nowrap">{{ $r->started_at->format('d/m/Y H:i') }}</td><td>{{ $r->tenant?->name }}</td><td class="small">{{ $r->mode === 'test' ? 'Uji restore (DB terpisah)' : 'Restore produksi' }}</td>
    <td><x-badge :status="['passed' => 'done', 'completed' => 'done', 'failed' => 'failed', 'running' => 'running'][$r->status]" :label="$r->status"/></td><td class="small faint">{{ $r->report['error'] ?? implode(' · ', $r->report['checks'] ?? []) }}{{ $r->reason ? ' · '.$r->reason : '' }}</td></tr>
  @empty <tr><td class="empty">Belum ada.</td></tr> @endforelse
</tbody></table></div></div>
@endsection
