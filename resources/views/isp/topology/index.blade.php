@extends('layouts.app')
@section('title', 'Peta Topologi')
@section('content')
{{-- Peta topologi jaringan dengan jalur animasi. @2026-Alifiandra --}}
@include('partials.leaflet')
@push('head')
<style>
  .topo-wrap { position: relative; }
  .topo-map { height: calc(100vh - 230px); min-height: 460px; border-radius: var(--radius); border: 1px solid var(--border); z-index: 0; background: var(--surface-2); }
  .topo-panel { position: absolute; top: 12px; right: 12px; z-index: 500; width: 260px; max-height: calc(100% - 24px); overflow: auto;
    background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow); padding: .75rem .85rem; font-size: .82rem; }
  .topo-panel h4 { margin: .2rem 0 .45rem; font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; color: var(--text-3); }
  .topo-panel label { display: flex; align-items: center; gap: .45rem; padding: .16rem 0; cursor: pointer; }
  .topo-panel .sw { width: 12px; height: 12px; border-radius: 50%; display: inline-block; flex: none; }
  .topo-panel .ln { width: 18px; height: 0; border-top: 3px solid; display: inline-block; flex: none; }
  .topo-panel .row { display: flex; justify-content: space-between; gap: .5rem; padding: .1rem 0; }
  .topo-panel input[type=search] { width: 100%; }
  .topo-results { margin-top: .35rem; }
  .topo-results button { display: block; width: 100%; text-align: left; background: none; border: 0; padding: .28rem .35rem; border-radius: 6px; color: var(--text); cursor: pointer; font: inherit; }
  .topo-results button:hover { background: var(--row-hover); }
  .topo-toggle { display: none; }
  /* Jalur animasi: dash bergerak dari sumber ke tujuan */
  .topo-line { stroke-dasharray: 10 12; animation: topo-flow 1.1s linear infinite; }
  .topo-line.slow { animation-duration: 1.8s; }
  .topo-line.down { animation: topo-flow 0.6s linear infinite, topo-blink 1s ease-in-out infinite; }
  .topo-static .topo-line { animation: none; }
  @keyframes topo-flow { to { stroke-dashoffset: -22; } }
  @keyframes topo-blink { 50% { stroke-opacity: .25; } }
  .topo-pulse { animation: topo-pulse 1.4s ease-out infinite; transform-origin: center; transform-box: fill-box; }
  @keyframes topo-pulse { 0% { stroke-opacity: .9; stroke-width: 2; } 100% { stroke-opacity: 0; stroke-width: 18; } }
  .topo-node { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; color: #fff; font-size: 10px; font-weight: 800; border: 2px solid #fff; box-shadow: 0 1px 5px rgba(0,0,0,.45); }
  .topo-node.router { background: #4338ca; } .topo-node.olt { background: #7c3aed; } .topo-node.other { background: #475569; }
  .topo-node.odc { background: #0e7490; border-radius: 50%; width: 26px; height: 26px; }
  .topo-node.is-down { background: #c22f2f !important; animation: topo-shake 1s ease-in-out infinite; }
  @keyframes topo-shake { 50% { box-shadow: 0 0 0 6px rgba(194,47,47,.35); } }
  @media (prefers-reduced-motion: reduce) { .topo-line, .topo-pulse, .topo-node.is-down { animation: none !important; } }
  @media (max-width: 860px) {
    .topo-map { height: calc(100vh - 200px); }
    .topo-panel { position: static; width: auto; max-height: none; margin-top: .75rem; box-shadow: none; }
  }
</style>
@endpush

<div class="page-head">
  <div><h1>Peta Topologi</h1><p>Jalur Router/OLT → ODC → ODP → pelanggan. Garis lurus antar titik adalah perkiraan, bukan rute kabel sebenarnya.</p></div>
  <div class="actions">
    <a href="{{ route('app.odps.index') }}" class="btn"><x-icon name="map"/> Daftar ODP</a>
    @can('network.odp.manage')<a href="{{ route('app.odps.create') }}" class="btn btn-primary"><x-icon name="plus"/> Titik ODP</a>@endcan
  </div>
</div>

@if ($data['missing']['odp'] || $data['missing']['customers'] || $data['missing']['unassigned'])
  <div class="alert alert-warning small">
    @if ($data['missing']['odp']){{ $data['missing']['odp'] }} ODP belum punya koordinat. @endif
    @if ($data['missing']['customers']){{ $data['missing']['customers'] }} layanan pelanggan belum punya koordinat rumah. @endif
    @if ($data['missing']['unassigned']){{ $data['missing']['unassigned'] }} pelanggan di peta belum dipasang ke port ODP (tanpa jalur). @endif
    Titik tanpa koordinat tidak tampil di peta.
  </div>
@endif

<div class="topo-wrap">
  <div id="topo-map" class="topo-map"></div>
  <div class="topo-panel" id="topo-panel">
    <input type="search" id="topo-search" class="input" placeholder="Cari pelanggan, ODP, ODC…" autocomplete="off">
    <div class="topo-results" id="topo-results"></div>

    <h4 style="margin-top:.7rem">Ringkasan</h4>
    <div class="row"><span>Router / OLT</span><strong id="c-dev">0</strong></div>
    <div class="row"><span>ODC</span><strong id="c-odc">0</strong></div>
    <div class="row"><span>ODP</span><strong id="c-odp">0</strong></div>
    <div class="row"><span>Pelanggan</span><strong id="c-cus">0</strong></div>
    <div class="row"><span style="color:var(--success)">● Online</span><strong id="c-on">–</strong></div>
    <div class="row"><span style="color:var(--danger)">● Offline</span><strong id="c-off">–</strong></div>
    <div class="small faint" id="topo-status-note">Memuat status PPPoE…</div>

    <h4 style="margin-top:.7rem">Lapisan</h4>
    <label><input type="checkbox" data-layer="devices" checked><span class="sw" style="background:#4338ca;border-radius:3px"></span> Router / OLT</label>
    <label><input type="checkbox" data-layer="odcs" checked><span class="sw" style="background:#0e7490"></span> ODC</label>
    <label><input type="checkbox" data-layer="odps" checked><span class="sw" style="background:#12805c"></span> ODP</label>
    <label><input type="checkbox" data-layer="customers" checked><span class="sw" style="background:#0b6bcb"></span> Pelanggan</label>
    <label><input type="checkbox" data-layer="backbone" checked><span class="ln" style="border-color:#7c3aed"></span> Jalur backbone</label>
    <label><input type="checkbox" data-layer="drops" checked><span class="ln" style="border-color:#0b6bcb;border-top-width:2px"></span> Jalur ke pelanggan</label>
    <label><input type="checkbox" id="topo-anim" checked> Animasi jalur</label>
    <label><input type="checkbox" id="topo-only-off"> Hanya pelanggan offline</label>

    <h4 style="margin-top:.7rem">Keterangan</h4>
    <div class="small"><span class="sw" style="background:#12805c"></span> Online · <span class="sw" style="background:#c22f2f"></span> Offline · <span class="sw" style="background:#d38a10"></span> Terisolir · <span class="sw" style="background:#0b6bcb"></span> Belum diketahui</div>
    <div class="small faint" style="margin-top:.3rem">ODP: warna = okupansi port. Jalur merah berkedip = bagian dari gangguan massal aktif / perangkat down.</div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var D = @json($data);
  var statusUrl = @json(route('app.topology.status'));
  var el = document.getElementById('topo-map');
  function init() {
    if (!window.L) { el.innerHTML = '<div class="empty">Peta tidak dapat dimuat. Periksa koneksi internet (Leaflet & OpenStreetMap).</div>'; return; }
    var esc = AlmaraMap.esc;
    var map = L.map(el, { preferCanvas: false, zoomControl: true });
    AlmaraMap.tiles(map);
    var layers = { devices: L.layerGroup(), odcs: L.layerGroup(), odps: L.layerGroup(), customers: L.layerGroup(), backbone: L.layerGroup(), drops: L.layerGroup() };
    // Urutan tumpukan: jalur di bawah, titik di atas.
    ['backbone', 'drops', 'odps', 'odcs', 'devices', 'customers'].forEach(function (k) { layers[k].addTo(map); });

    var dev = {}, odc = {}, odp = {}, bounds = [], searchIndex = [];
    function nodeIcon(cls, label, down) {
      return L.divIcon({ className: '', html: '<div class="topo-node ' + cls + (down ? ' is-down' : '') + '">' + esc(label) + '</div>', iconSize: [30, 30], iconAnchor: [15, 15] });
    }
    function line(a, b, opt, cls) {
      return L.polyline([a, b], Object.assign({ className: 'topo-line ' + (cls || ''), lineCap: 'round', interactive: false }, opt));
    }

    D.devices.forEach(function (d) {
      var cls = d.type === 'mikrotik' ? 'router' : (d.type === 'olt' ? 'olt' : 'other');
      var lbl = d.type === 'mikrotik' ? 'RT' : (d.type === 'olt' ? 'OLT' : 'SW');
      var down = d.status === 'down' || d.incident;
      dev[d.id] = d;
      L.marker([d.lat, d.lng], { icon: nodeIcon(cls, lbl, down), zIndexOffset: 1000 }).addTo(layers.devices)
        .bindPopup('<strong>' + esc(d.name) + '</strong><br><span class="mono">' + esc(d.host) + '</span><br>Status monitoring: ' + esc(d.status || 'belum dipantau') + (d.incident ? '<br><strong style="color:#c22f2f">Terdampak gangguan massal</strong>' : ''));
      bounds.push([d.lat, d.lng]);
      searchIndex.push({ label: d.name, sub: d.type.toUpperCase(), lat: d.lat, lng: d.lng });
    });

    D.odcs.forEach(function (o) {
      odc[o.id] = o;
      L.marker([o.lat, o.lng], { icon: L.divIcon({ className: '', html: '<div class="topo-node odc' + (o.incident ? ' is-down' : '') + '">ODC</div>', iconSize: [26, 26], iconAnchor: [13, 13] }), zIndexOffset: 800 })
        .addTo(layers.odcs).bindPopup('<strong>' + esc(o.code) + '</strong><br>' + esc(o.name) + (o.incident ? '<br><strong style="color:#c22f2f">Terdampak gangguan massal</strong>' : ''));
      var src = o.device_id && dev[o.device_id];
      if (src) line([src.lat, src.lng], [o.lat, o.lng], { color: o.incident || src.status === 'down' ? '#c22f2f' : '#7c3aed', weight: 4, opacity: .85 }, (o.incident || src.status === 'down') ? 'down' : 'slow').addTo(layers.backbone);
      bounds.push([o.lat, o.lng]);
      searchIndex.push({ label: o.code + ' · ' + o.name, sub: 'ODC', lat: o.lat, lng: o.lng });
    });

    D.odps.forEach(function (o) {
      odp[o.id] = o;
      var lvl = o.status !== 'active' ? 'muted' : AlmaraMap.level(o.used, o.capacity);
      L.marker([o.lat, o.lng], { icon: L.divIcon({ className: '', html: '<div class="odp-marker ' + (o.incident ? 'danger' : lvl) + '">' + o.used + '</div>', iconSize: [26, 26], iconAnchor: [13, 13] }), zIndexOffset: 600 })
        .addTo(layers.odps).bindPopup('<strong>' + esc(o.code) + '</strong><br>' + esc(o.name) + '<br>Port terpakai: ' + o.used + '/' + o.capacity + (o.incident ? '<br><strong style="color:#c22f2f">Terdampak gangguan massal</strong>' : '') + '<br><a href="' + o.url + '">Buka detail ODP</a>');
      var src = (o.odc_id && odc[o.odc_id]) || (o.device_id && dev[o.device_id]);
      if (src) {
        var bad = o.incident || src.incident || src.status === 'down';
        line([src.lat, src.lng], [o.lat, o.lng], { color: bad ? '#c22f2f' : '#0e7490', weight: 3, opacity: .85 }, bad ? 'down' : '').addTo(layers.backbone);
      }
      if (o.incident) L.circleMarker([o.lat, o.lng], { radius: 14, color: '#c22f2f', fill: false, weight: 2, className: 'topo-pulse', interactive: false }).addTo(layers.odps);
      bounds.push([o.lat, o.lng]);
      searchIndex.push({ label: o.code + ' · ' + o.name, sub: 'ODP · ' + o.used + '/' + o.capacity, lat: o.lat, lng: o.lng });
    });

    var cust = {};
    var COLORS = { online: '#12805c', offline: '#c22f2f', isolated: '#d38a10', unknown: '#0b6bcb' };
    D.customers.forEach(function (c) {
      var marker = L.circleMarker([c.lat, c.lng], { radius: 6, color: '#fff', weight: 2, fillColor: COLORS.unknown, fillOpacity: 1 })
        .bindPopup(function () {
          var st = c.state === 'online' ? '<span style="color:#12805c">● Online</span>' : (c.state === 'offline' ? '<span style="color:#c22f2f">● Offline</span>' : 'Status PPPoE belum diketahui');
          return '<strong>' + esc(c.name) + '</strong> (' + esc(c.code) + ')<br>' + esc(c.package || '') + (c.status === 'isolated' ? ' · <strong style="color:#d38a10">Terisolir</strong>' : '')
            + '<br>' + st + (c.pppoe ? '<br>PPPoE: <span class="mono">' + esc(c.pppoe) + '</span>' : '')
            + (c.odp_id && odp[c.odp_id] ? '<br>ODP ' + esc(odp[c.odp_id].code) + ' · port ' + c.port : '<br><em>Belum terpasang ke ODP</em>')
            + '<br><a href="' + c.url + '">Buka pelanggan</a>';
        });
      marker.addTo(layers.customers);
      var o = c.odp_id && odp[c.odp_id], drop = null;
      if (o) drop = line([o.lat, o.lng], [c.lat, c.lng], { color: o.incident ? '#c22f2f' : COLORS.unknown, weight: 1.8, opacity: .75 }, o.incident ? 'down' : '').addTo(layers.drops);
      cust[c.id] = { data: c, marker: marker, drop: drop };
      bounds.push([c.lat, c.lng]);
      searchIndex.push({ label: c.name, sub: c.code + (c.pppoe ? ' · ' + c.pppoe : ''), lat: c.lat, lng: c.lng, ref: marker });
    });

    document.getElementById('c-dev').textContent = D.devices.length;
    document.getElementById('c-odc').textContent = D.odcs.length;
    document.getElementById('c-odp').textContent = D.odps.length;
    document.getElementById('c-cus').textContent = D.customers.length;

    var wide = window.innerWidth > 860;
    if (bounds.length) map.fitBounds(bounds, { paddingTopLeft: [40, 40], paddingBottomRight: [wide ? 300 : 40, 40], maxZoom: 17 }); else map.setView([-2.5, 118], 5);
    // Animasi dimatikan otomatis bila jalur sangat banyak (agar browser tetap ringan).
    var anim = document.getElementById('topo-anim');
    if (D.customers.length > 1500) { anim.checked = false; el.classList.add('topo-static'); }
    anim.addEventListener('change', function () { el.classList.toggle('topo-static', !anim.checked); });

    document.querySelectorAll('[data-layer]').forEach(function (cb) {
      cb.addEventListener('change', function () { var g = layers[cb.dataset.layer]; cb.checked ? g.addTo(map) : map.removeLayer(g); });
    });

    var onlyOff = document.getElementById('topo-only-off');
    function applyFilter() {
      Object.keys(cust).forEach(function (id) {
        var x = cust[id], show = !onlyOff.checked || x.data.state === 'offline';
        if (show) { x.marker.addTo(layers.customers); if (x.drop) x.drop.addTo(layers.drops); }
        else { layers.customers.removeLayer(x.marker); if (x.drop) layers.drops.removeLayer(x.drop); }
      });
    }
    onlyOff.addEventListener('change', applyFilter);

    // Pencarian titik
    var input = document.getElementById('topo-search'), results = document.getElementById('topo-results');
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase(); results.innerHTML = '';
      if (q.length < 2) return;
      searchIndex.filter(function (s) { return (s.label + ' ' + s.sub).toLowerCase().indexOf(q) !== -1; }).slice(0, 8).forEach(function (s) {
        var b = document.createElement('button'); b.type = 'button';
        b.innerHTML = '<strong>' + esc(s.label) + '</strong><div class="small faint">' + esc(s.sub) + '</div>';
        b.addEventListener('click', function () { map.setView([s.lat, s.lng], 18); if (s.ref) s.ref.openPopup(); results.innerHTML = ''; });
        results.appendChild(b);
      });
      if (!results.children.length) results.innerHTML = '<div class="small faint" style="padding:.3rem">Tidak ditemukan.</div>';
    });

    // Status online/offline PPPoE (async, diperbarui tiap 60 detik)
    var note = document.getElementById('topo-status-note');
    function paint() {
      var on = 0, off = 0;
      Object.keys(cust).forEach(function (id) {
        var x = cust[id], st = x.data.state, color = st === 'online' ? COLORS.online : (st === 'offline' ? COLORS.offline : COLORS.unknown);
        if (x.data.status === 'isolated' && st !== 'online') color = COLORS.isolated;
        if (st === 'online') on++; if (st === 'offline') off++;
        x.marker.setStyle({ fillColor: color });
        if (x.drop && !(x.data.odp_id && odp[x.data.odp_id] && odp[x.data.odp_id].incident)) {
          x.drop.setStyle({ color: color });
          var p = x.drop.getElement && x.drop.getElement();
          if (p) p.classList.toggle('down', st === 'offline');
        }
      });
      document.getElementById('c-on').textContent = on;
      document.getElementById('c-off').textContent = off;
    }
    function loadStatus() {
      fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (s) {
          if (!s.ok) { note.textContent = s.message || 'Status PPPoE tidak tersedia.'; return; }
          Object.keys(cust).forEach(function (id) { cust[id].data.state = s.services[id] || null; });
          paint(); applyFilter();
          if (!s.routers) note.textContent = 'Belum ada router MikroTik dengan username API — status online belum tersedia.';
          else if (s.errors.length) note.textContent = 'Sebagian router gagal dibaca (' + s.errors.map(function (e) { return e.router; }).join(', ') + '); status offline tidak ditampilkan.';
          else note.textContent = 'Status PPPoE diperbarui ' + new Date().toLocaleTimeString('id-ID') + '.';
        })
        .catch(function () { note.textContent = 'Status PPPoE gagal dimuat.'; });
    }
    loadStatus();
    setInterval(loadStatus, 60000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
@endpush
@endsection
