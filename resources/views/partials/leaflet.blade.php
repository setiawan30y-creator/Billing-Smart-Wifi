{{-- Leaflet.js + OpenStreetMap. @2026-Alifiandra --}}
@once
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
  .map { height: 420px; border-radius: var(--radius); border: 1px solid var(--border); z-index: 0; }
  .map-sm { height: 280px; }
  .leaflet-popup-content { font-family: var(--font); font-size: 13px; }
  .odp-marker { width: 26px; height: 26px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,.4); display: grid; place-items: center; color: #fff; font-size: 10px; font-weight: 800; }
  .odp-marker.success { background: #12805c; } .odp-marker.warning { background: #d38a10; } .odp-marker.danger { background: #c22f2f; } .odp-marker.muted { background: #6b7a90; }
  .cust-marker { width: 12px; height: 12px; border-radius: 50%; background: #0b6bcb; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,.4); }
  .map-legend { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .78rem; color: var(--text-2); margin-top: .5rem; }
  .map-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: .3rem; vertical-align: middle; }
</style>
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
window.AlmaraMap = {
  tiles: function (map) {
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20, maxNativeZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
  },
  level: function (used, cap) { if (!cap) return 'muted'; var p = used * 100 / cap; return p >= 100 ? 'danger' : (p >= 75 ? 'warning' : 'success'); },
  esc: function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; },
  /** Peta jaringan: ODP (warna = okupansi port) + pelanggan + garis drop kabel (perkiraan, garis lurus). */
  network: function (el, data) {
    var map = L.map(el, { scrollWheelZoom: false }); this.tiles(map);
    var bounds = [], byId = {}, self = this;
    data.odps.forEach(function (o) {
      var icon = L.divIcon({ className: '', html: '<div class="odp-marker ' + (o.status !== 'active' ? 'muted' : self.level(o.used, o.capacity)) + '">' + o.used + '</div>', iconSize: [26, 26], iconAnchor: [13, 13] });
      L.marker([o.lat, o.lng], { icon: icon }).addTo(map).bindPopup('<strong>' + self.esc(o.code) + '</strong><br>' + self.esc(o.name) + '<br>Port terpakai: ' + o.used + '/' + o.capacity + '<br><a href="' + o.url + '">Buka detail</a>');
      bounds.push([o.lat, o.lng]); byId[o.id] = o;
    });
    data.customers.forEach(function (c) {
      var o = byId[c.odp_id];
      if (o) L.polyline([[o.lat, o.lng], [c.lat, c.lng]], { color: '#0b6bcb', weight: 1.5, opacity: .6, dashArray: '4 4' }).addTo(map);
      L.marker([c.lat, c.lng], { icon: L.divIcon({ className: '', html: '<div class="cust-marker"></div>', iconSize: [12, 12], iconAnchor: [6, 6] }) })
        .addTo(map).bindPopup('<strong>' + self.esc(c.name) + '</strong> (' + self.esc(c.code) + ')<br>Port ' + c.port + '<br><a href="' + c.url + '">Buka pelanggan</a>');
      bounds.push([c.lat, c.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 17 }); else map.setView([-2.5, 118], 5);
    return map;
  },
  /** Pemilih titik: klik peta / geser pin / tombol lokasi saya → isi input latitude & longitude. */
  picker: function (el, latInput, lngInput, others) {
    var map = L.map(el); this.tiles(map); var marker = null, self = this;
    (others || []).forEach(function (o) {
      L.circleMarker([o.lat, o.lng], { radius: 6, color: '#6b7a90', weight: 2, fillOpacity: .5 }).addTo(map).bindTooltip(self.esc(o.label));
    });
    function set(lat, lng, pan) {
      lat = Math.round(lat * 1e7) / 1e7; lng = Math.round(lng * 1e7) / 1e7;
      latInput.value = lat; lngInput.value = lng;
      if (!marker) { marker = L.marker([lat, lng], { draggable: true }).addTo(map); marker.on('dragend', function () { var p = marker.getLatLng(); set(p.lat, p.lng, false); }); }
      else marker.setLatLng([lat, lng]);
      if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 17));
    }
    map.on('click', function (e) { set(e.latlng.lat, e.latlng.lng, false); });
    function fromInputs() { var a = parseFloat(latInput.value), b = parseFloat(lngInput.value); if (!isNaN(a) && !isNaN(b)) set(a, b, true); }
    latInput.addEventListener('change', fromInputs); lngInput.addEventListener('change', fromInputs);
    if (latInput.value && lngInput.value) fromInputs();
    else if (others && others.length) map.fitBounds(others.map(function (o) { return [o.lat, o.lng]; }), { padding: [30, 30], maxZoom: 16 });
    else map.setView([-2.5, 118], 5);
    return {
      locate: function (btn) {
        if (!navigator.geolocation) { alert('Browser tidak mendukung lokasi.'); return; }
        btn.disabled = true; btn.textContent = 'Mencari lokasi…';
        navigator.geolocation.getCurrentPosition(function (p) { set(p.coords.latitude, p.coords.longitude, true); btn.disabled = false; btn.textContent = 'Gunakan lokasi saya'; },
          function () { btn.disabled = false; btn.textContent = 'Gunakan lokasi saya'; alert('Lokasi tidak dapat diambil. Pastikan izin lokasi aktif (butuh HTTPS atau localhost).'); },
          { enableHighAccuracy: true, timeout: 15000 });
      }
    };
  }
};
</script>
@endpush
@endonce
