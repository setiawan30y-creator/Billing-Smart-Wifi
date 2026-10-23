{{-- Pemilih koordinat. Parameter: $lat, $lng (nilai awal), $others (array [lat,lng,label]) --}}
@include('partials.leaflet')
<div class="field full">
  <label>Titik lokasi di peta</label>
  <div id="picker-map" class="map map-sm"></div>
  <div class="actions" style="margin-top:.5rem">
    <button type="button" class="btn btn-sm" id="picker-locate"><x-icon name="pin"/> Gunakan lokasi saya</button>
    <span class="small muted">Klik peta atau geser pin untuk menentukan titik. Titik abu-abu = ODP lain.</span>
  </div>
</div>
<x-field name="latitude" label="Latitude" :value="$lat" placeholder="-6.2000000" inputmode="decimal"/>
<x-field name="longitude" label="Longitude" :value="$lng" placeholder="106.8166667" inputmode="decimal"/>
@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var p = AlmaraMap.picker(document.getElementById('picker-map'), document.getElementById('f-latitude'), document.getElementById('f-longitude'), @json($others ?? []));
    document.getElementById('picker-locate').addEventListener('click', function () { p.locate(this); });
  });
</script>
@endpush
