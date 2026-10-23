@extends('layouts.app')
@section('title', $customer->name)
@section('content')
<div class="page-head">
  <div><h1>{{ $customer->name }} <x-badge :status="$customer->status" :label="\App\Models\Customer::STATUSES[$customer->status]"/></h1><p class="mono">{{ $customer->customer_code }}</p></div>
  <div class="actions">
    @can('customers.update')<a href="{{ route('app.customers.edit', $customer) }}" class="btn">Ubah</a>@endcan
    @can('invoices.create')<a href="{{ route('app.invoices.create', ['customer' => $customer->id]) }}" class="btn">Invoice manual</a>@endcan
    @can('payments.create')<a href="{{ route('app.payments.create', ['customer' => $customer->id]) }}" class="btn btn-primary">Catat pembayaran</a>@endcan
  </div>
</div>
<div class="grid grid-main">
  <div class="stack">
    <div class="card"><div class="card-head"><h3>Layanan internet</h3></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Paket</th><th class="num">Harga</th><th>Tagih</th><th>Mulai</th><th>Status</th><th></th></tr></thead><tbody>
      @forelse ($customer->services as $s)
        <tr>
          <td><strong>{{ $s->package->name }}</strong>@if ($s->pppoe_username)<div class="small mono faint">PPPoE: {{ $s->pppoe_username }}</div>@endif
            <div class="small">@if ($s->odpPort)<a href="{{ route('app.odps.show', $s->odpPort->odp_id) }}"><x-icon name="pin" style="width:13px;height:13px;vertical-align:-2px;stroke:currentColor;fill:none;stroke-width:2"/> {{ $s->odpPort->odp->code }} · port {{ $s->odpPort->port_number }}</a>@elseif ($s->status !== 'terminated')<span class="faint">Belum terpasang ke ODP</span>@endif</div></td>
          <td class="num">{{ rupiah($s->monthlyPrice()) }}@if ($s->price_override !== null)<div class="small faint">harga khusus</div>@endif</td>
          <td class="small">tgl {{ $s->billing_day }} · tempo {{ $s->due_days }} hr</td>
          <td class="small">{{ tgl($s->started_at) }}@if ($s->ended_at)<br>s/d {{ tgl($s->ended_at) }}@endif</td>
          <td><x-badge :status="$s->status" :label="\App\Models\CustomerService::STATUSES[$s->status]"/></td>
          <td></td>
        </tr>
        @can('services.manage')
        <tr><td colspan="6" style="padding:0;border:0">
          <details style="padding:0 1rem"><summary class="small muted" style="cursor:pointer;padding:.4rem 0">Ubah layanan {{ $s->package->name }}</summary>
          <form method="POST" action="{{ route('app.services.update', $s) }}" class="form-grid" style="padding:.75rem 0 1rem">@csrf @method('PUT')
            <div class="field"><label>Paket</label><select name="service_package_id" class="input">@foreach ($packages as $p)<option value="{{ $p->id }}" @selected($p->id === $s->service_package_id)>{{ $p->name }}</option>@endforeach @if (! $packages->contains('id', $s->service_package_id))<option value="{{ $s->service_package_id }}" selected>{{ $s->package->name }}</option>@endif</select></div>
            <div class="field"><label>Status</label><select name="status" class="input">@foreach (\App\Models\CustomerService::STATUSES as $k => $l)<option value="{{ $k }}" @selected($s->status === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label>Harga khusus (Rp)</label><input name="price_override" class="input" value="{{ $s->price_override !== null ? (int) $s->price_override : '' }}" placeholder="Kosong = harga paket"></div>
            <div class="field"><label>PPPoE username</label><input name="pppoe_username" class="input" value="{{ $s->pppoe_username }}"></div>
            <div class="field"><label>Tanggal tagih</label><input type="number" name="billing_day" min="1" max="28" class="input" value="{{ $s->billing_day }}"></div>
            <div class="field"><label>Jatuh tempo (hari)</label><input type="number" name="due_days" min="0" max="60" class="input" value="{{ $s->due_days }}"></div>
            @can('network.odp.manage')
            <div class="field full"><label>Port ODP</label><select name="odp_port_id" class="input">
              <option value="">— Tidak terpasang —</option>
              @if ($s->odpPort)<option value="{{ $s->odpPort->id }}" selected>{{ $s->odpPort->odp->code }} · port {{ $s->odpPort->port_number }} (saat ini)</option>@endif
              @foreach ($freePorts->groupBy(fn ($p) => $p->odp->code.' · '.$p->odp->name) as $grp => $ports)
                <optgroup label="{{ $grp }}">@foreach ($ports as $fp)<option value="{{ $fp->id }}">{{ $fp->odp->code }} · port {{ $fp->port_number }}</option>@endforeach</optgroup>
              @endforeach
            </select><div class="hint">Hanya port kosong dari ODP aktif yang ditampilkan. Layanan berstatus "Berhenti" otomatis melepas port.</div></div>
            @endcan
            <div class="field full"><label>Alasan perubahan</label><input name="reason" class="input" placeholder="Opsional, dicatat di audit"></div>
            <div class="full"><button class="btn btn-primary btn-sm">Simpan layanan</button> <span class="small muted">Status "Terisolir" hanya mencatat status di billing; tidak mengubah konfigurasi router.</span></div>
          </form></details></td></tr>
        @endcan
      @empty <tr><td colspan="6" class="empty">Belum ada layanan.</td></tr> @endforelse
      </tbody></table></div>
      @can('services.manage')
      <details class="card-body" style="border-top:1px solid var(--border)"><summary style="cursor:pointer;font-weight:600">+ Tambah layanan</summary>
        <form method="POST" action="{{ route('app.services.store', $customer) }}" class="form-grid" style="margin-top:1rem">@csrf
          <div class="field"><label class="req">Paket</label><select name="service_package_id" class="input" required>@foreach ($packages as $p)<option value="{{ $p->id }}">{{ $p->name }} · {{ rupiah($p->price) }}</option>@endforeach</select></div>
          <div class="field"><label class="req">Mulai</label><input type="date" name="started_at" class="input" value="{{ now()->toDateString() }}" required></div>
          <div class="field"><label class="req">Tanggal tagih</label><input type="number" name="billing_day" class="input" value="1" min="1" max="28" required></div>
          <div class="field"><label class="req">Jatuh tempo (hari setelah terbit)</label><input type="number" name="due_days" class="input" value="10" min="0" max="60" required></div>
          <div class="field"><label>Harga khusus (Rp)</label><input name="price_override" class="input" placeholder="Kosong = harga paket"></div>
          <div class="field"><label>PPPoE username</label><input name="pppoe_username" class="input"></div>
          @can('network.odp.manage')
          <div class="field full"><label>Port ODP (opsional)</label><select name="odp_port_id" class="input"><option value="">— Pasang nanti —</option>
            @foreach ($freePorts->groupBy(fn ($p) => $p->odp->code.' · '.$p->odp->name) as $grp => $ports)<optgroup label="{{ $grp }}">@foreach ($ports as $fp)<option value="{{ $fp->id }}">{{ $fp->odp->code }} · port {{ $fp->port_number }}</option>@endforeach</optgroup>@endforeach
          </select></div>
          @endcan
          <div class="full"><button class="btn btn-primary">Tambah layanan</button></div>
        </form></details>
      @endcan
    </div>

    <div class="card"><div class="card-head"><h3>Invoice</h3><span class="small muted">Sisa tagihan: <strong>{{ rupiah($outstanding) }}</strong></span></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Nomor</th><th>Periode</th><th>Jatuh tempo</th><th class="num">Total</th><th class="num">Sisa</th><th>Status</th></tr></thead><tbody>
      @forelse ($invoices as $i)
        <tr><td><a class="mono small" href="{{ route('app.invoices.show', $i) }}">{{ $i->number }}</a></td><td>{{ $i->period ?? '-' }}</td><td class="small">{{ tgl($i->due_date) }}</td><td class="num">{{ rupiah($i->total) }}</td><td class="num">{{ rupiah($i->balance()) }}</td><td><x-badge :status="$i->isOverdue() ? 'failed' : $i->status" :label="$i->isOverdue() ? 'Overdue' : \App\Models\Invoice::STATUSES[$i->status]"/></td></tr>
      @empty <tr><td colspan="6" class="empty">Belum ada invoice.</td></tr> @endforelse
      </tbody></table></div></div>

    <div class="card"><div class="card-head"><h3>Pembayaran</h3></div>
      <div class="table-wrap"><table class="table"><tbody>
      @forelse ($payments as $p)
        <tr><td><a class="mono small" href="{{ route('app.payments.show', $p) }}">{{ $p->number }}</a><div class="small faint">{{ $p->paid_at->format('d/m/Y') }} · {{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}</div></td><td class="num">{{ rupiah($p->amount) }}</td><td class="right"><x-badge :status="$p->status" :label="\App\Models\Payment::STATUSES[$p->status]"/></td></tr>
      @empty <tr><td class="empty">Belum ada pembayaran.</td></tr> @endforelse
      </tbody></table></div></div>
  </div>

  <div class="stack">
    <div class="card"><div class="card-body"><dl class="kv" style="grid-template-columns:110px 1fr">
      <dt>WhatsApp</dt><dd>{{ $customer->phone }}</dd>
      <dt>Email</dt><dd>{{ $customer->email ?: '-' }}</dd>
      <dt>Alamat</dt><dd>{{ $customer->address }}</dd>
      <dt>Patokan</dt><dd>{{ $customer->landmark ?: '-' }}</dd>
      <dt>Rumah</dt><dd>{{ $customer->ownership === 'rent' ? 'Kontrak · pemilik: '.$customer->owner_name : 'Milik pribadi' }}</dd>
      <dt>Lokasi</dt><dd>@if ($customer->latitude){{ $customer->latitude }}, {{ $customer->longitude }}<br><a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat={{ $customer->latitude }}&mlon={{ $customer->longitude }}#map=18/{{ $customer->latitude }}/{{ $customer->longitude }}">Buka peta</a>@endif @if ($customer->maps_url) · <a target="_blank" rel="noopener" href="{{ $customer->maps_url }}">Google Maps</a>@endif @if (! $customer->latitude && ! $customer->maps_url)-@endif</dd>
      <dt>NIK</dt><dd class="mono">{{ $customer->maskedNik() }}</dd>
      <dt>Berkas</dt><dd>
        @if ($customer->ktp_path) @can('customers.sensitive.view')<a href="{{ route('app.customers.file', [$customer, 'ktp']) }}" target="_blank">KTP (depan)</a>@else<span class="faint">KTP tersimpan</span>@endcan @endif
        @if ($customer->house_photo_path) · <a href="{{ route('app.customers.file', [$customer, 'rumah']) }}" target="_blank">Foto rumah</a>@endif
        @if (! $customer->ktp_path && ! $customer->house_photo_path)-@endif</dd>
      @if ($customer->notes)<dt>Catatan</dt><dd>{{ $customer->notes }}</dd>@endif
    </dl></div></div>
  </div>
</div>
@endsection
