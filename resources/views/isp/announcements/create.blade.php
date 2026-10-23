@extends('layouts.app')
@section('title', 'Pengumuman baru')
@section('content')
<div class="page-head"><h1>Pengumuman baru</h1><a href="{{ route('app.announcements.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ route('app.announcements.store') }}" class="card" style="max-width:900px" x-data="{ mode: '{{ old('target_mode', $incident ? 'incident' : 'all') }}' }">@csrf
  <div class="card-body form-grid">
    <x-field name="title" label="Judul" :value="$incident ? 'Info gangguan: '.$incident->title : null" required full/>
    <x-field name="body" label="Isi pesan" type="textarea" full rows="5" required :value="$incident ? 'Saat ini terjadi gangguan layanan di area Anda. Tim teknis sedang menangani.'.($incident->estimated_recovery_at ? ' Estimasi pulih: '.$incident->estimated_recovery_at->format('d/m/Y H:i').'.' : '').' Mohon maaf atas ketidaknyamanannya.' : null"/>
    <div class="field full"><label class="req">Penerima</label><select name="target_mode" class="input" x-model="mode">@foreach (\App\Models\ServiceAnnouncement::MODES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
    <div class="field full" x-show="mode === 'incident'"><label>Insiden</label><select name="network_incident_id" class="input"><option value="">Pilih</option>@foreach ($incidents as $i)<option value="{{ $i->id }}" @selected(($incident?->id ?? old('network_incident_id')) == $i->id)>{{ $i->number }} · {{ $i->title }}</option>@endforeach</select></div>
    @foreach (['odp' => [$odps, fn ($o) => $o->code.' · '.$o->name], 'odc' => [$odcs, fn ($o) => $o->code.' · '.$o->name], 'device' => [$devices, fn ($o) => $o->name], 'package' => [$packages, fn ($o) => $o->name]] as $mode => [$list, $label])
      <div class="field full" x-show="mode === '{{ $mode }}'" x-cloak><label>Pilih</label><div class="grid grid-3" style="max-height:220px;overflow:auto">@foreach ($list as $o)<label class="check small"><input type="checkbox" name="targets[]" value="{{ $o->id }}" :disabled="mode !== '{{ $mode }}'"> {{ $label($o) }}</label>@endforeach</div></div>
    @endforeach
    <x-field name="visible_until" label="Tampil di portal sampai (opsional)" type="datetime-local"/>
    <div class="field" style="align-self:end;display:grid;gap:.4rem">
      <label class="check"><input type="checkbox" name="send_whatsapp" value="1" checked> Kirim WhatsApp</label>
      <label class="check"><input type="checkbox" name="show_in_portal" value="1" checked> Tampilkan di portal pelanggan</label>
    </div>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border)"><button class="btn btn-primary">Simpan draf & lihat penerima</button></div>
</form>
@endsection
