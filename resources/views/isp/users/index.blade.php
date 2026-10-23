@extends('layouts.app')
@section('title', 'Pengguna & Role')
@section('content')
<div class="page-head"><div><h1>Pengguna & Role</h1><p>Akses staf ISP. Hak akses ditegakkan di server sesuai role.</p></div><a href="{{ route('app.users.create') }}" class="btn btn-primary"><x-icon name="plus"/> Pengguna</a></div>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Nama</th><th>Role</th><th>Login terakhir</th><th>Status</th><th></th></tr></thead>
  <tbody>
  @foreach ($users as $u)
    <tr><td><strong>{{ $u->name }}</strong><div class="small muted">{{ $u->email }}</div></td><td class="small">{{ $u->roles->pluck('name')->implode(', ') }}</td>
      <td class="small">{{ $u->last_login_at?->format('d/m/Y H:i') ?? '-' }}</td>
      <td>@if (! $u->password)<x-badge status="pending" label="Belum aktivasi"/>@elseif ($u->is_active)<x-badge status="active" label="Aktif"/>@else<x-badge status="void" label="Nonaktif"/>@endif</td>
      <td class="right"><div class="actions" style="justify-content:flex-end">
        @if (! $u->password)<form method="POST" action="{{ route('app.users.activation', $u) }}">@csrf<button class="btn btn-sm">Link aktivasi</button></form>@endif
        <a class="btn btn-sm" href="{{ route('app.users.edit', $u) }}">Ubah</a></div></td></tr>
  @endforeach
  </tbody></table></div></div>
@endsection
