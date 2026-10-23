@extends('layouts.guest')
@section('title', 'Lupa password')
@section('content')
  <h1>Lupa password</h1>
  <p class="muted">Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat password baru.</p>
  @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  <form method="POST" action="{{ route('password.email') }}" class="stack">
    @csrf
    <div class="field"><label for="email">Email</label><input id="email" class="input" type="email" name="email" value="{{ old('email') }}" placeholder="nama@perusahaan.com" required autofocus autocomplete="username"></div>
    <button class="btn btn-primary btn-block" type="submit">Kirim tautan reset</button>
  </form>
  <p class="small" style="margin-top:1rem"><a href="{{ route('login') }}">← Kembali ke halaman masuk</a></p>
@endsection
