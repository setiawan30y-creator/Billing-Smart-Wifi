@extends('layouts.guest')
@section('title', 'Reset password')
@section('content')
  <h1>Buat password baru</h1>
  @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('password.update') }}" class="stack">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field"><label for="email">Email</label><input id="email" class="input" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"></div>
    <div class="field"><label for="password">Password baru</label><input id="password" class="input" type="password" name="password" required autofocus autocomplete="new-password"><div class="hint">Minimal 10 karakter, mengandung huruf dan angka.</div></div>
    <div class="field"><label for="password_confirmation">Ulangi password baru</label><input id="password_confirmation" class="input" type="password" name="password_confirmation" required autocomplete="new-password"></div>
    <button class="btn btn-primary btn-block" type="submit">Simpan password</button>
  </form>
  <p class="small" style="margin-top:1rem"><a href="{{ route('login') }}">← Kembali ke halaman masuk</a></p>
@endsection
