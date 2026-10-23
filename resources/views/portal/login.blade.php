@extends('portal.layout')
@section('title', 'Masuk')
@section('content')
<div style="max-width:420px;margin:3rem auto 0">
  <div class="center" style="margin-bottom:1.5rem">
    <div class="brand-mark" style="width:64px;height:64px;margin:0 auto .8rem;font-size:1.5rem;border-radius:16px">@if ($tenant->logo_path)<img src="{{ asset('storage/'.$tenant->logo_path) }}" alt="">@else A @endif</div>
    <h1>{{ $tenant->displayName() }}</h1>
    <p class="muted">Portal pelanggan — cek tagihan, kirim bukti bayar, dan laporkan gangguan.</p>
  </div>
  <div class="card"><form method="POST" action="{{ url('portal/'.$tenant->code.'/login') }}" class="card-body stack">
    @csrf
    <div class="field"><label for="customer_code">ID pelanggan</label><input id="customer_code" name="customer_code" class="input" value="{{ old('customer_code') }}" placeholder="mis. PLG-00001" required autofocus autocomplete="username" style="text-transform:uppercase"></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" class="input" required autocomplete="current-password"></div>
    <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
    <button class="btn btn-primary btn-block">Masuk</button>
    <p class="small muted" style="margin:0">Belum punya password atau lupa? Hubungi {{ $tenant->displayName() }} untuk mendapatkan tautan aktivasi.</p>
  </form></div>
</div>
@endsection
