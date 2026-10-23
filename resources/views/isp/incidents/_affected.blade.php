@php $sel = $selected ?? []; @endphp
<div class="grid grid-3">
  <div><div class="label">ODP</div><div style="max-height:220px;overflow:auto">@forelse ($odps as $o)<label class="check small" style="display:flex"><input type="checkbox" name="affected[]" value="odp:{{ $o->id }}" @checked(in_array('odp:'.$o->id, $sel))> {{ $o->code }} · {{ $o->name }}</label>@empty<span class="small faint">Belum ada ODP</span>@endforelse</div></div>
  <div><div class="label">ODC</div><div style="max-height:220px;overflow:auto">@forelse ($odcs as $o)<label class="check small" style="display:flex"><input type="checkbox" name="affected[]" value="odc:{{ $o->id }}" @checked(in_array('odc:'.$o->id, $sel))> {{ $o->code }} · {{ $o->name }}</label>@empty<span class="small faint">Belum ada ODC</span>@endforelse</div></div>
  <div><div class="label">OLT / perangkat</div><div style="max-height:220px;overflow:auto">@forelse ($devices as $d)<label class="check small" style="display:flex"><input type="checkbox" name="affected[]" value="device:{{ $d->id }}" @checked(in_array('device:'.$d->id, $sel))> {{ $d->name }}</label>@empty<span class="small faint">Belum ada perangkat</span>@endforelse</div></div>
</div>
<p class="small muted" style="margin:.5rem 0 0">Pelanggan pada perangkat dihitung lewat ODP/ODC yang terhubung ke perangkat tersebut. Pelanggan yang belum dipasang ke port ODP tidak ikut terhitung.</p>
