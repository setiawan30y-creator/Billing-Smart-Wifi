@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
  <h1>Masuk</h1>
  <p class="muted">Gunakan akun yang diberikan administrator Anda.</p>
  @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  <form method="POST" action="{{ url('/login') }}" class="stack">
    @csrf
    <div class="field"><label for="email">Email</label><input id="email" class="input" type="email" name="email" value="{{ old('email') }}" placeholder="nama@perusahaan.com" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password">Password</label><input id="password" class="input" type="password" name="password" required autocomplete="current-password"></div>
    <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
    <button class="btn btn-primary btn-block" type="submit">Masuk</button>
  </form>
@endsection
