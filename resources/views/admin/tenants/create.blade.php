@extends('layouts.app')
@section('title', 'Tambah ISP')
@section('content')
<div class="page-head"><div><h1>Tambah ISP</h1><p>Daftarkan perusahaan ISP baru sebagai tenant.</p></div><a href="{{ route('admin.tenants.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ route('admin.tenants.store') }}" enctype="multipart/form-data" class="card" x-data="{ status: '{{ old('initial_status', 'trial') }}' }">
  @csrf
  @include('admin.tenants._profile_fields', ['tenant' => new \App\Models\Tenant(['theme' => 'default', 'timezone' => 'Asia/Jakarta']), 'creating' => true])
  <div class="card-body form-section">
    <p class="section-title">B. Paket & langganan SaaS</p>
    <div class="form-grid">
      <div class="field"><label class="req">Paket SaaS</label>
        <select name="saas_plan_id" class="input" required>
          <option value="">Pilih paket dari katalog</option>
          @foreach ($plans as $p)
            <option value="{{ $p->id }}" @selected(old('saas_plan_id') == $p->id)>{{ $p->name }} — {{ $p->price_monthly !== null ? rupiah($p->price_monthly).'/bln' : 'Custom' }} · {{ $p->client_quota ? number_format($p->client_quota, 0, ',', '.').' client' : 'kuota kontrak' }} · {{ $p->router_quota ?? '-' }} MikroTik{{ $p->is_price_approved ? '' : ' (harga usulan)' }}</option>
          @endforeach
        </select>
        <div class="hint">Harga, fitur, dan kuota diambil dari katalog paket — bukan input bebas.</div></div>
      <div class="field"><label class="req">Periode</label>
        <select name="period" class="input"><option value="monthly">Bulanan</option><option value="yearly" @selected(old('period') === 'yearly')>Tahunan</option></select></div>
      <div class="field"><label class="req">Status awal</label>
        <select name="initial_status" class="input" x-model="status"><option value="trial">Trial</option><option value="active">Aktif</option></select></div>
      <div class="field" x-show="status === 'trial'"><label>Durasi trial (hari)</label><input type="number" name="trial_days" class="input" min="1" max="90" value="{{ old('trial_days', 14) }}"></div>
    </div>
  </div>
  <div class="card-body form-section">
    <p class="section-title">D. Administrator pertama</p>
    <div class="form-grid">
      <x-field name="admin_name" label="Nama administrator" required placeholder="Nama lengkap"/>
      <x-field name="admin_email" label="Email login" type="email" required placeholder="email@perusahaan.com"/>
      <p class="full small muted" style="margin:0">Akun diberi role <strong>Admin ISP</strong>. Password tidak dibuat oleh Super Admin — administrator menerima tautan aktivasi sekali pakai (email). Bila email tidak terkirim, link aktivasi manual dapat dibuat di halaman detail ISP.</p>
    </div>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border);display:flex;gap:.5rem;justify-content:flex-end">
    <a href="{{ route('admin.tenants.index') }}" class="btn">Batal</a>
    <button class="btn btn-primary" type="submit">Simpan & provisioning</button>
  </div>
</form>
@endsection
