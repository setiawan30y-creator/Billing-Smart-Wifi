@extends('layouts.app')
@section('title', 'Ubah template')
@section('content')
<div class="page-head"><div><h1>{{ \App\Models\NotificationTemplate::EVENTS[$template->event] }}</h1></div><a href="{{ route('app.templates.index') }}" class="btn">Kembali</a></div>
<div class="grid grid-main" x-data="{ body: {{ \Illuminate\Support\Js::from(old('body', $template->body)) }}, preview: {{ \Illuminate\Support\Js::from($preview) }}, unknown: [],
  async refresh() { const r = await fetch('{{ route('app.templates.preview') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ body: this.body }) }); const j = await r.json(); this.preview = j.preview; this.unknown = j.unknown; },
  insert(p) { this.body += '{' + p + '}'; this.refresh(); } }">
  <form method="POST" action="{{ route('app.templates.update', $template) }}" class="card">@csrf @method('PUT')
    <div class="card-body stack">
      <div class="field"><label class="req">Isi pesan</label><textarea name="body" class="input mono" rows="9" x-model="body" @input.debounce.400ms="refresh()" maxlength="2000"></textarea>
        @error('body')<div class="error">{{ $message }}</div>@enderror
        <div class="error" x-show="unknown.length" x-cloak>Placeholder tidak dikenal: <span x-text="unknown.map(u => '{' + u + '}').join(', ')"></span></div></div>
      <div><div class="label">Sisipkan placeholder</div><div class="actions">
        @foreach (\App\Models\NotificationTemplate::PLACEHOLDERS as $k => $l)<button type="button" class="btn btn-sm" title="{{ $l }}" @click="insert('{{ $k }}')">{{ '{'.$k.'}' }}</button>@endforeach
      </div></div>
      <label class="check"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $template->is_enabled))> Aktifkan template ini</label>
      <div><button class="btn btn-primary">Simpan template</button></div>
    </div>
  </form>
  <div class="stack">
    <div class="card"><div class="card-head"><h3>Preview</h3><span class="small faint">data contoh</span></div><div class="card-body"><div style="white-space:pre-wrap;background:var(--success-50);padding:.9rem;border-radius:10px" x-text="preview"></div></div></div>
    @can('notifications.test_send')
    <div class="card"><div class="card-head"><h3>Kirim tes</h3></div>
      <form method="POST" action="{{ route('app.templates.test', $template) }}" class="card-body" style="display:flex;gap:.4rem">@csrf
        <input name="number" class="input" placeholder="08xxxxxxxxxx" required><button class="btn">Kirim</button></form>
      <p class="small faint" style="padding:0 1.25rem 1rem;margin:0">Memakai template tersimpan. Simpan dulu bila ada perubahan.</p></div>
    @endcan
  </div>
</div>
@endsection
