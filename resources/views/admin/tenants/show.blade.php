@extends('layouts.app')
@section('title', $tenant->name)
@section('content')
@php $sub = $tenant->subscriptions->whereIn('status', ['trial','active','past_due'])->sortByDesc('starts_at')->first(); @endphp
<div class="page-head">
  <div>
    <h1>{{ $tenant->name }} <x-badge :status="$tenant->status" :label="$tenant->statusLabel()"/></h1>
    <p class="mono">{{ $tenant->code }} · dibuat {{ tgl($tenant->created_at, 'd M Y H:i') }}</p>
  </div>
  <div class="actions" x-data="{ open: null }">
    @can('tenants.update')<a href="{{ route('admin.tenants.edit', $tenant) }}" class="btn">Ubah data</a>@endcan
    @can('tenants.suspend')
      @if ($tenant->canTransitionTo('suspended'))
        <button class="btn btn-danger" @click="open = 'suspend'">Tangguhkan</button>
      @endif
      @foreach (['active' => 'Aktifkan', 'trial' => 'Jadikan trial'] as $st => $lbl)
        @if ($tenant->canTransitionTo($st) && $tenant->status !== 'provisioning')
          <form method="POST" action="{{ route('admin.tenants.status', $tenant) }}">@csrf<input type="hidden" name="status" value="{{ $st }}"><button class="btn">{{ $lbl }}</button></form>
        @endif
      @endforeach
    @endcan
    <template x-if="open === 'suspend'">
      <div class="modal-backdrop" @click.self="open = null"><div class="modal"><form method="POST" action="{{ route('admin.tenants.status', $tenant) }}" class="card-body stack">
        @csrf <input type="hidden" name="status" value="suspended">
        <h3>Tangguhkan {{ $tenant->name }}?</h3>
        <p class="small muted">Akses tenant ditahan. Data pelanggan, invoice, dan riwayat <strong>tidak dihapus</strong>.</p>
        <div class="field"><label class="req">Alasan</label><input name="reason" class="input" required maxlength="250"></div>
        <div class="actions" style="justify-content:flex-end"><button type="button" class="btn" @click="open = null">Batal</button><button class="btn btn-danger">Tangguhkan</button></div>
      </form></div></div>
    </template>
  </div>
</div>

<div class="grid grid-main">
  <div class="stack">
    <div class="card"><div class="card-head"><h2>Profil perusahaan</h2></div><div class="card-body">
      <dl class="kv">
        <dt>PIC</dt><dd>{{ $tenant->profile?->pic_name }}</dd>
        <dt>WhatsApp</dt><dd>{{ $tenant->profile?->whatsapp }}</dd>
        <dt>Email</dt><dd>{{ $tenant->profile?->email }}</dd>
        <dt>Telepon kantor</dt><dd>{{ $tenant->profile?->office_phone ?: '-' }}</dd>
        <dt>Alamat</dt><dd>{{ $tenant->profile?->address }}, {{ $tenant->profile?->city }}, {{ $tenant->profile?->province }}, {{ $tenant->profile?->country }}</dd>
        <dt>Website</dt><dd>{{ $tenant->profile?->website ?: '-' }}</dd>
        <dt>Brand</dt><dd>{{ $tenant->displayName() }} · tema {{ $tenant->theme }} · {{ $tenant->timezone }}</dd>
        @if ($tenant->suspended_reason)<dt>Alasan suspend</dt><dd>{{ $tenant->suspended_reason }}</dd>@endif
      </dl>
    </div></div>

    <div class="card">
      <div class="card-head"><h2>Langganan SaaS</h2></div>
      <div class="card-body">
        @if ($sub)
          <dl class="kv">
            <dt>Paket</dt><dd><strong>{{ $sub->plan->name }}</strong> ({{ $sub->period === 'yearly' ? 'tahunan' : 'bulanan' }}) <x-badge :status="$sub->status" :label="\App\Models\TenantSubscription::STATUSES[$sub->status] ?? $sub->status"/></dd>
            <dt>Harga (snapshot)</dt><dd>{{ $sub->price !== null ? rupiah($sub->price) : 'Sesuai kontrak' }} @unless ($sub->plan->is_price_approved)<span class="badge badge-warning">harga usulan</span>@endunless</dd>
            <dt>Periode</dt><dd>{{ tgl($sub->starts_at) }} – {{ tgl($sub->ends_at) }} @if ($sub->trial_ends_at) · trial s/d {{ tgl($sub->trial_ends_at) }} @endif · tenggang {{ $sub->grace_days }} hari</dd>
            <dt>Overage</dt><dd>{{ rupiah($sub->overage_client_price) }}/client · {{ rupiah($sub->overage_router_price) }}/router</dd>
          </dl>
        @else
          <p class="muted">Tidak ada langganan aktif.</p>
        @endif
        <div class="grid grid-2" style="margin-top:1rem">
          @foreach (['clients' => 'Client aktif', 'routers' => 'MikroTik'] as $k => $label)
            @php $q = $quota[$k]; @endphp
            <div>
              <div class="small muted">{{ $label }}: <strong>{{ $q['used'] }}</strong> / {{ $q['quota'] ?? '∞' }} @if ($q['over'])<span class="badge badge-danger">+{{ $q['over'] }} di atas kuota</span>@endif</div>
              @if ($q['quota'])<div class="progress {{ $q['level'] }}" style="margin-top:.35rem"><span style="width:{{ min(100, $q['percent']) }}%"></span></div>@endif
            </div>
          @endforeach
        </div>
      </div>
      @can('saas_subscriptions.manage')
      <details class="card-body" style="border-top:1px solid var(--border)">
        <summary style="cursor:pointer;font-weight:600">Ubah paket langganan</summary>
        <form method="POST" action="{{ route('admin.tenants.plan', $tenant) }}" class="form-grid" style="margin-top:1rem">
          @csrf
          <div class="field"><label class="req">Paket baru</label><select name="saas_plan_id" class="input" required>@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
          <div class="field"><label class="req">Periode</label><select name="period" class="input"><option value="monthly">Bulanan</option><option value="yearly">Tahunan</option></select></div>
          <div class="field"><label class="req">Status</label><select name="status" class="input"><option value="active">Aktif</option><option value="trial">Trial</option></select></div>
          <div class="field"><label class="req">Tanggal efektif</label><input type="date" name="effective_date" class="input" value="{{ now()->toDateString() }}" required></div>
          <div class="field full"><label class="req">Alasan perubahan</label><input name="reason" class="input" required maxlength="250"></div>
          <div class="full"><button class="btn btn-primary">Terapkan</button> <span class="small muted">Langganan lama diakhiri sehari sebelum tanggal efektif; kontrak lama tidak diubah diam-diam.</span></div>
        </form>
      </details>
      @endcan
    </div>

    <div class="card"><div class="card-head"><h2>Pengguna tenant</h2></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Nama</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
        @foreach ($tenant->users as $u)
          <tr><td>{{ $u->name }}<div class="small muted">{{ $u->email }}</div></td>
            <td class="small">{{ $u->roles->pluck('name')->implode(', ') }}</td>
            <td>@if ($u->password)<x-badge status="active" label="Aktif"/>@else<x-badge status="pending" label="Belum aktivasi"/>@endif</td>
            <td class="right">@if (! $u->password)
              @can('tenants.update')<form method="POST" action="{{ route('admin.tenants.activation', $tenant) }}">@csrf<input type="hidden" name="user_id" value="{{ $u->id }}"><button class="btn btn-sm">Buat link aktivasi</button></form>@endcan
            @endif</td></tr>
        @endforeach
      </tbody></table></div>
    </div>

    <div class="card"><div class="card-head"><h2>Riwayat & audit</h2></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Waktu</th><th>Tindakan</th><th>Oleh</th></tr></thead><tbody>
        @forelse ($logs as $l)
          <tr><td class="small nowrap">{{ $l->created_at->timezone($tenant->timezone)->format('d/m/Y H:i') }}</td><td class="mono small">{{ $l->action }}@if ($l->reason)<div class="muted">{{ $l->reason }}</div>@endif</td><td class="small">{{ $l->user?->name ?? 'Sistem' }}</td></tr>
        @empty <tr><td colspan="3" class="empty">Belum ada riwayat.</td></tr> @endforelse
      </tbody></table></div>
    </div>
  </div>

  <div class="stack">
    <div class="card"><div class="card-head"><h3>Status provisioning</h3>
      @if ($tenant->provisioningJobs->where('status', '!=', 'done')->count())
        @can('tenants.update')<form method="POST" action="{{ route('admin.tenants.retry', $tenant) }}">@csrf<button class="btn btn-sm"><x-icon name="refresh"/> Ulangi</button></form>@endcan
      @endif
    </div><div class="card-body">
      @foreach ($tenant->provisioningJobs as $j)
        <div style="display:flex;justify-content:space-between;gap:.5rem;margin-bottom:.6rem">
          <div><div>{{ \App\Models\TenantProvisioningJob::STEPS[$j->step] ?? $j->step }}</div>
            @if ($j->last_error)<div class="small" style="color:var(--danger)">{{ $j->last_error }}</div>@endif
            <div class="small faint">percobaan {{ $j->attempts }}</div></div>
          <x-badge :status="$j->status"/>
        </div>
      @endforeach
      @if ($tenant->provisioningJobs->where('status', 'pending')->count() && config('queue.default') !== 'sync')
        <p class="small muted">Menunggu worker antrean (<span class="mono">php artisan queue:work</span>).</p>
      @endif
    </div></div>

    <div class="card"><div class="card-head"><h3>Domain</h3></div><div class="card-body">
      @forelse ($tenant->domains as $d)
        <div style="margin-bottom:.8rem">
          <div style="display:flex;justify-content:space-between;gap:.5rem"><strong class="mono small">{{ $d->domain }}</strong><x-badge :status="$d->status === 'verified' ? 'active' : ($d->status === 'failed' ? 'failed' : 'pending')" :label="$d->status"/></div>
          @if ($d->status !== 'verified')
            <div class="small muted">Tambahkan TXT record <span class="mono">_almara.{{ $d->domain }}</span> bernilai <span class="mono" style="word-break:break-all">{{ $d->verification_token }}</span></div>
            @if ($d->last_error)<div class="small" style="color:var(--danger)">{{ $d->last_error }}</div>@endif
            @can('tenants.update')<form method="POST" action="{{ route('admin.tenants.domains.verify', [$tenant, $d]) }}" style="margin-top:.3rem">@csrf<button class="btn btn-sm">Verifikasi DNS</button></form>@endcan
          @endif
        </div>
      @empty
        <p class="small muted">Belum ada domain{{ $tenant->subdomain ? ' (subdomain: '.$tenant->subdomain.')' : '' }}.</p>
      @endforelse
      @can('tenants.update')
      <form method="POST" action="{{ route('admin.tenants.domains.store', $tenant) }}" style="display:flex;gap:.4rem;margin-top:.5rem">@csrf<input name="domain" class="input" placeholder="billing.domainisp.com" required><button class="btn btn-sm">Tambah</button></form>
      @endcan
    </div></div>
  </div>
</div>
@endsection
