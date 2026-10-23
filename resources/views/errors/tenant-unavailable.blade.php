@extends('layouts.guest')
@section('title', 'Akses ditahan')
@section('content')
  <h1>Akses sementara ditahan</h1>
  <p class="muted">
    @if ($tenant?->status === 'suspended')
      Layanan platform untuk <strong>{{ $tenant->name }}</strong> sedang ditangguhkan. Data Anda tetap aman dan tidak dihapus.
      @if ($tenant->suspended_reason)<br>Alasan: {{ $tenant->suspended_reason }}@endif
    @else
      Akun ISP <strong>{{ $tenant?->name }}</strong> belum aktif (status: {{ $tenant?->statusLabel() }}).
    @endif
  </p>
  <p class="muted">Silakan hubungi pengelola platform ALMARA.</p>
  <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn">Keluar</button></form>
@endsection
