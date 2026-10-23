@extends('portal.layout')
@section('title', 'Aktivasi')
@section('content')
<div style="max-width:420px;margin:3rem auto 0">
  <h1>Aktivasi portal</h1>
  <p class="muted">Halo <strong>{{ $customer->name }}</strong> (ID {{ $customer->customer_code }}). Buat password untuk masuk ke portal {{ $tenant->displayName() }}.</p>
  <div class="card"><form method="POST" action="{{ route('portal.activate', [$tenant->code, $token]) }}" class="card-body stack">
    @csrf
    <div class="field"><label for="password">Password baru</label><input id="password" type="password" name="password" class="input" required autocomplete="new-password"><div class="hint">Minimal 8 karakter, berisi huruf dan angka.</div></div>
    <div class="field"><label for="password_confirmation">Ulangi password</label><input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password"></div>
    <button class="btn btn-primary btn-block">Aktifkan & masuk</button>
  </form></div>
</div>
@endsection
