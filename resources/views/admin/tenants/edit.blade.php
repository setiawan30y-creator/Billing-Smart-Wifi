@extends('layouts.app')
@section('title', 'Ubah ISP')
@section('content')
<div class="page-head"><div><h1>Ubah {{ $tenant->name }}</h1></div><a href="{{ route('admin.tenants.show', $tenant) }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" enctype="multipart/form-data" class="card">
  @csrf @method('PUT')
  @include('admin.tenants._profile_fields', ['creating' => false])
  <div class="card-body" style="border-top:1px solid var(--border);display:flex;justify-content:flex-end"><button class="btn btn-primary">Simpan perubahan</button></div>
</form>
@endsection
