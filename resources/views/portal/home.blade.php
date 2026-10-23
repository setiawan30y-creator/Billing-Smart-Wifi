@extends('portal.layout')
@section('title', 'Beranda')
@section('content')
<div class="hero" style="margin-bottom:1rem">
  <div class="small muted">Halo,</div>
  <h2 style="margin:.1rem 0 .3rem;color:#fff">{{ $customer->name }}</h2>
  <div class="small muted">ID pelanggan {{ $customer->customer_code }}</div>
  <div style="margin-top:1rem;display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap">
    <div><div class="small muted">Total tagihan belum dibayar</div><div style="font-size:1.8rem;font-weight:800">{{ rupiah($outstanding) }}</div></div>
    @if ($openInvoices->count())<a href="{{ route('portal.invoices.pay', [$tenant->code, $openInvoices->first()->id]) }}" class="btn" style="background:#fff;color:#0b4f96;border-color:#fff">Bayar sekarang</a>@endif
  </div>
</div>

@foreach ($announcements as $a)
  <div class="alert alert-warning"><strong>{{ $a->title }}</strong><div style="white-space:pre-wrap">{{ $a->body }}</div><div class="small" style="margin-top:.3rem">{{ $a->created_at->format('d/m/Y H:i') }}</div></div>
@endforeach

<div class="card" style="margin-bottom:1rem"><div class="card-head"><h3>Layanan saya</h3></div>
  @forelse ($customer->services->where('status', '!=', 'terminated') as $s)
    <div class="list-item"><div><strong>{{ $s->package->name }}</strong><div class="small muted">{{ $s->package->speedLabel() }} · {{ rupiah($s->monthlyPrice()) }}/bulan · tagih tgl {{ $s->billing_day }}</div></div>
      <x-badge :status="$s->status" :label="\App\Models\CustomerService::STATUSES[$s->status]"/></div>
  @empty <div class="card-body muted small">Belum ada layanan aktif.</div> @endforelse
</div>

<div class="card" style="margin-bottom:1rem"><div class="card-head"><h3>Tagihan belum lunas</h3><a href="{{ route('portal.invoices', $tenant->code) }}" class="small">Semua</a></div>
  @forelse ($openInvoices as $i)
    <a class="list-item" href="{{ route('portal.invoices.pay', [$tenant->code, $i->id]) }}"><div><strong>{{ $i->period ? 'Periode '.$i->period : $i->number }}</strong><div class="small muted">Jatuh tempo {{ tgl($i->due_date) }}</div></div>
      <div class="right"><strong>{{ rupiah($i->balance()) }}</strong><div><x-badge :status="$i->isOverdue() ? 'failed' : 'pending'" :label="$i->isOverdue() ? 'Lewat jatuh tempo' : 'Belum bayar'"/></div></div></a>
  @empty <div class="card-body small" style="color:var(--success)">Tidak ada tagihan tertunggak. Terima kasih!</div> @endforelse
</div>

<div class="grid grid-2">
  <a href="{{ route('portal.tickets.create', $tenant->code) }}" class="card card-body" style="color:var(--text)"><strong>Laporkan gangguan</strong><div class="small muted">{{ $openTickets ? $openTickets.' tiket sedang diproses' : 'Kirim tiket ke tim teknis' }}</div></a>
  <a href="https://wa.me/{{ \App\Services\NotificationService::normalizeNumber($tenant->profile?->whatsapp ?? '') }}" target="_blank" rel="noopener" class="card card-body" style="color:var(--text)"><strong>Hubungi {{ $tenant->displayName() }}</strong><div class="small muted">WhatsApp {{ $tenant->profile?->whatsapp }}</div></a>
</div>
@endsection
