@extends('layouts.guest')
@section('title', 'Aktivasi akun')
@section('content')
  <h1>Aktivasi akun</h1>
  <p class="muted">Halo <strong>{{ $invitation->user->name }}</strong>, buat password untuk akun <strong>{{ $invitation->user->email }}</strong>{{ $invitation->user->tenant ? ' di '.$invitation->user->tenant->name : '' }}.</p>
  @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('activation.store', $token) }}" class="stack">
    @csrf
    <div class="field"><label for="password">Password baru</label><input id="password" class="input" type="password" name="password" required autocomplete="new-password"><div class="hint">Minimal 10 karakter, mengandung huruf dan angka.</div></div>
    <div class="field"><label for="password_confirmation">Ulangi password</label><input id="password_confirmation" class="input" type="password" name="password_confirmation" required autocomplete="new-password"></div>
    <button class="btn btn-primary btn-block" type="submit">Aktifkan akun</button>
  </form>
  <p class="small faint" style="margin-top:1rem">Tautan berlaku sampai {{ $invitation->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} dan hanya dapat dipakai sekali.</p>
@endsection
