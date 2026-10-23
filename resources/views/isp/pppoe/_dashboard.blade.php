{{-- Widget dashboard: pelanggan PPPoE online/offline. @2026-Alifiandra --}}
@if ($s['routers'] === 0)
  <div class="card-body small muted">Belum ada router MikroTik aktif dengan username API.
    @can('monitoring.devices.manage')<a href="{{ route('app.devices.index') }}">Atur router</a>@endcan</div>
@else
  @php $total = max(1, $s['online'] + $s['offline'] + $s['unknown']); @endphp
  <div class="card-body">
    <div class="pp-stats">
      <div><div class="stat-label">Online</div><div class="stat-value" style="color:var(--success)">{{ number_format($s['online'], 0, ',', '.') }}</div></div>
      <div><div class="stat-label">Offline</div><div class="stat-value" style="color:var(--danger)">{{ $s['errors'] ? '—' : number_format($s['offline'], 0, ',', '.') }}</div></div>
      <div><div class="stat-label">Terisolir tapi online</div><div class="stat-value" @if ($s['isolated_online']) style="color:var(--warning)" @endif>{{ $s['isolated_online'] }}</div></div>
    </div>
    <div class="pp-bar" title="Online {{ $s['online'] }} · Offline {{ $s['offline'] }}">
      <span style="width:{{ round($s['online'] / $total * 100, 2) }}%;background:var(--success)"></span>
      <span style="width:{{ round($s['offline'] / $total * 100, 2) }}%;background:var(--danger)"></span>
      <span style="width:{{ round($s['unknown'] / $total * 100, 2) }}%;background:var(--text-3)"></span>
    </div>
    <div class="small faint" style="margin-top:.4rem">{{ $s['tracked'] }} layanan dipantau dari {{ $s['routers'] }} router
      @if ($s['without_username']) · {{ $s['without_username'] }} layanan aktif belum diisi username PPPoE @endif
      @if ($s['fetched_at']) · data {{ \Illuminate\Support\Carbon::parse($s['fetched_at'])->timezone(config('app.timezone'))->format('H:i:s') }} @endif</div>
    @foreach ($s['errors'] as $e)
      <div class="alert alert-warning small" style="margin:.6rem 0 0">Router <strong>{{ $e['router'] }}</strong> gagal dibaca: {{ $e['message'] }}. Status offline tidak ditampilkan agar tidak menyesatkan.</div>
    @endforeach
  </div>
  @if ($s['isolated_list']->isNotEmpty() || $s['offline_list']->isNotEmpty())
    <div class="table-wrap"><table class="table"><thead><tr><th>Pelanggan</th><th>Username PPPoE</th><th>Status</th></tr></thead><tbody>
      @foreach ($s['isolated_list']->take(5) as $r)
        <tr><td>@include('isp.pppoe._customer', ['svc' => $r['service']])</td><td class="mono small">{{ $r['service']->pppoe_username }}</td>
          <td><span class="badge badge-warning">Terisolir · online</span><div class="small faint mono">{{ $r['session']['address'] ?? '' }}</div></td></tr>
      @endforeach
      @foreach ($s['offline_list']->take(10) as $r)
        <tr><td>@include('isp.pppoe._customer', ['svc' => $r['service']])</td><td class="mono small">{{ $r['service']->pppoe_username }}</td>
          <td><span class="badge badge-danger">Offline</span></td></tr>
      @endforeach
    </tbody></table></div>
    @if ($s['offline'] > 10)<div class="card-body small muted" style="padding-top:.5rem">+{{ $s['offline'] - 10 }} pelanggan offline lainnya.</div>@endif
  @endif
@endif
