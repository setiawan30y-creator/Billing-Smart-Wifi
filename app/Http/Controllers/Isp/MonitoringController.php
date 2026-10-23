<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\MonitoringAlert;
use App\Models\MonitoringCollectionRun;
use App\Models\MonitoringTarget;
use App\Models\NetworkDevice;
use App\Services\AuditLogger;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Monitoring Jaringan (read-only): target, sampel, alarm, status collector.
 * @2026-Alifiandra
 */
class MonitoringController extends Controller
{
    public function index()
    {
        $tenant = $this->tenant();

        return view('isp.monitoring.index', [
            'targets' => MonitoringTarget::with('device')->orderByRaw("case last_status when 'down' then 0 when 'degraded' then 1 when 'unknown' then 2 else 3 end")->orderBy('name')->get(),
            'alerts' => MonitoringAlert::with('target')->whereIn('status', ['open', 'acknowledged'])->latest('started_at')->get(),
            'lastRun' => MonitoringCollectionRun::where('tenant_id', $tenant->id)->latest('id')->first(),
            'failedRuns' => MonitoringCollectionRun::where('tenant_id', $tenant->id)->where('started_at', '>=', now()->subDay())->where(fn ($q) => $q->whereNotNull('error')->orWhere('collector_errors', '>', 0))->count(),
            'unmonitored' => NetworkDevice::where('is_active', true)->whereDoesntHave('monitoringTargets')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $device = $request->integer('device') ? NetworkDevice::find($request->integer('device')) : null;

        return view('isp.monitoring.form', [
            'target' => new MonitoringTarget([
                'network_device_id' => $device?->id, 'name' => $device?->name, 'host' => $device?->host,
                'method' => $device?->type === 'mikrotik' ? 'routeros' : 'icmp', 'port' => $device?->api_port ?? 8728,
                'enabled' => true, 'interval_minutes' => 5, 'latency_warn_ms' => 150, 'loss_warn_pct' => 20, 'down_after_failures' => 2,
            ]),
            'devices' => NetworkDevice::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $t = MonitoringTarget::create($this->validated($request));
        AuditLogger::log('monitoring.target_created', $t, null, $t->only(['name', 'host', 'method']));

        return redirect()->route('app.monitoring.show', $t)->with('success', 'Target monitoring ditambahkan. Klik "Cek sekarang" untuk sampel pertama.');
    }

    public function show(Request $request, MonitoringTarget $target)
    {
        $hours = in_array((int) $request->query('h'), [6, 24, 72, 168], true) ? (int) $request->query('h') : 24;
        $samples = $target->samples()->where('measured_at', '>=', now()->subHours($hours))->orderBy('measured_at')->get();

        return view('isp.monitoring.show', [
            'target' => $target->load('device'),
            'samples' => $samples,
            'recent' => $samples->sortByDesc('measured_at')->take(30),
            'alerts' => $target->alerts()->latest('started_at')->limit(20)->get(),
            'hours' => $hours,
            'uptimePct' => ($n = $samples->whereIn('status', ['up', 'down'])->count()) ? round($samples->where('status', 'up')->count() * 100 / $n, 2) : null,
        ]);
    }

    public function edit(MonitoringTarget $target)
    {
        return view('isp.monitoring.form', ['target' => $target, 'devices' => NetworkDevice::orderBy('name')->get()]);
    }

    public function update(Request $request, MonitoringTarget $target)
    {
        $original = $target->getOriginal();
        $target->update($this->validated($request));
        AuditLogger::changes('monitoring.target_updated', $target, $original);

        return redirect()->route('app.monitoring.show', $target)->with('success', 'Target diperbarui.');
    }

    public function destroy(MonitoringTarget $target)
    {
        AuditLogger::log('monitoring.target_deleted', $target, $target->only(['name', 'host']));
        $target->delete();

        return redirect()->route('app.monitoring.index')->with('success', 'Target monitoring dihapus beserta sampelnya.');
    }

    public function checkNow(MonitoringTarget $target, MonitoringService $service)
    {
        $r = $service->check($target->load('device'));

        return back()->with($r->status === 'error' ? 'error' : 'success', match ($r->status) {
            'up' => 'Terjangkau'.($r->latencyMs !== null ? " · {$r->latencyMs} ms" : '').($r->error ? ' · '.$r->error : ''),
            'down' => 'Tidak terjangkau: '.$r->error,
            default => 'Collector gagal mengukur (bukan berarti perangkat mati): '.$r->error,
        });
    }

    public function alerts(Request $request)
    {
        return view('isp.monitoring.alerts', [
            'alerts' => MonitoringAlert::with(['target', 'acknowledger'])->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest('started_at')->paginate(30)->withQueryString(),
        ]);
    }

    public function acknowledge(Request $request, MonitoringAlert $alert)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:190']]);
        if ($alert->status === 'open') {
            $alert->update(['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now(), 'ack_note' => $data['note'] ?? null]);
            AuditLogger::log('monitoring.alert_ack', $alert, ['status' => 'open'], ['status' => 'acknowledged'], $data['note'] ?? null);
        }

        return back()->with('success', 'Alarm di-acknowledge.');
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'ticket_threshold' => ['required', 'integer', 'between:2,100'],
            'window_minutes' => ['required', 'integer', 'between:10,1440'],
        ]);
        $tenant = $this->tenant();
        $settings = $tenant->settings ?? [];
        $before = $settings['incident'] ?? null;
        $settings['incident'] = ['ticket_threshold' => (int) $data['ticket_threshold'], 'window_minutes' => (int) $data['window_minutes']];
        $tenant->settings = $settings;
        $tenant->save();
        AuditLogger::log('monitoring.settings', $tenant, ['incident' => $before], ['incident' => $settings['incident']]);

        return back()->with('success', 'Ambang deteksi gangguan massal disimpan.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'network_device_id' => ['nullable', Rule::exists('network_devices', 'id')->where('tenant_id', $this->tenant()->id), 'required_if:method,routeros'],
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-:]+$/'],
            'method' => ['required', Rule::in(array_keys(MonitoringTarget::METHODS))],
            'port' => ['nullable', 'required_if:method,tcp', 'integer', 'between:1,65535'],
            'interface_name' => ['nullable', 'string', 'max:60'],
            'interval_minutes' => ['required', 'integer', 'between:1,60'],
            'latency_warn_ms' => ['required', 'integer', 'between:1,10000'],
            'loss_warn_pct' => ['required', 'integer', 'between:1,100'],
            'down_after_failures' => ['required', 'integer', 'between:1,10'],
        ], ['network_device_id.required_if' => 'Metode RouterOS membutuhkan perangkat MikroTik dari registry (kredensial API diambil dari sana).']);
        $data['enabled'] = $request->boolean('enabled');

        return $data;
    }
}
