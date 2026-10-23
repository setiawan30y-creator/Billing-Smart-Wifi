<?php

namespace App\Services;

use App\Integrations\Monitoring\IcmpProbe;
use App\Integrations\Monitoring\ProbeResult;
use App\Integrations\Monitoring\RouterOsProbe;
use App\Integrations\Monitoring\TcpProbe;
use App\Models\MonitoringAlert;
use App\Models\MonitoringCollectionRun;
use App\Models\MonitoringSample;
use App\Models\MonitoringTarget;
use App\Models\Tenant;
use App\Support\TenantContext;
use Throwable;

/**
 * Collector monitoring read-only. Menyimpan hanya nilai yang benar-benar terukur,
 * memisahkan kegagalan collector (status "error") dari perangkat tidak terjangkau ("down").
 * @2026-Alifiandra
 */
class MonitoringService
{
    public function __construct(protected TcpProbe $tcp, protected IcmpProbe $icmp, protected RouterOsProbe $routeros) {}

    public function collect(Tenant $tenant, bool $force = false): MonitoringCollectionRun
    {
        $run = MonitoringCollectionRun::create(['tenant_id' => $tenant->id, 'started_at' => now()]);

        try {
            app(TenantContext::class)->run($tenant, function () use ($run, $force) {
                $targets = MonitoringTarget::with('device')->where('enabled', true)->get()->filter(fn ($t) => $force || $t->isDue());
                $up = $down = $err = 0;
                foreach ($targets as $t) {
                    $r = $this->check($t);
                    $r->status === 'up' ? $up++ : ($r->status === 'down' ? $down++ : $err++);
                }
                $run->update(['targets' => $targets->count(), 'up' => $up, 'down' => $down, 'collector_errors' => $err]);
            });
        } catch (Throwable $e) {
            $run->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
        }
        $run->update(['finished_at' => now()]);

        return $run;
    }

    public function check(MonitoringTarget $t): ProbeResult
    {
        try {
            $r = match ($t->method) {
                'icmp' => $this->icmp->check($t->host),
                'routeros' => $t->device ? $this->routeros->check($t->device, $t->interface_name) : new ProbeResult('routeros', 'error', error: 'Target RouterOS harus terhubung ke perangkat MikroTik di registry.'),
                default => $this->tcp->check($t->host, (int) ($t->port ?: 80)),
            };
        } catch (Throwable $e) {
            $r = new ProbeResult($t->method, 'error', error: 'Collector error: '.mb_substr($e->getMessage(), 0, 160));
        }
        $this->record($t, $r);

        return $r;
    }

    public function record(MonitoringTarget $t, ProbeResult $r): void
    {
        MonitoringSample::create([
            'monitoring_target_id' => $t->id, 'measured_at' => now(), 'source' => $r->source, 'status' => $r->status,
            'latency_ms' => $r->latencyMs, 'packet_loss_pct' => $r->lossPct, 'cpu_load_pct' => $r->cpuPct, 'memory_used_pct' => $r->memoryPct,
            'uptime_seconds' => $r->uptimeSeconds, 'rx_bps' => $r->rxBps, 'tx_bps' => $r->txBps, 'pppoe_active' => $r->pppoeActive,
            'error' => $r->error ? mb_substr($r->error, 0, 250) : null,
        ]);

        // Status target: error collector tidak mengubah status jaringan (tetap status terakhir yang terukur).
        if ($r->status === 'error') {
            $t->forceFill(['last_checked_at' => now(), 'last_error' => mb_substr((string) $r->error, 0, 250)])->save();

            return;
        }

        $fails = $r->status === 'down' ? $t->consecutive_failures + 1 : 0;
        $status = $r->status === 'down'
            ? ($fails >= $t->down_after_failures ? 'down' : ($t->last_status === 'down' ? 'down' : 'degraded'))
            : ((($r->latencyMs ?? 0) > $t->latency_warn_ms || ($r->lossPct ?? 0) >= $t->loss_warn_pct) ? 'degraded' : 'up');

        $t->forceFill([
            'last_status' => $status, 'last_checked_at' => now(), 'last_latency_ms' => $r->latencyMs, 'last_loss_pct' => $r->lossPct,
            'consecutive_failures' => $fails, 'last_error' => $r->status === 'down' ? mb_substr((string) $r->error, 0, 250) : null,
        ])->save();

        $this->evaluateAlerts($t, $r, $fails);
    }

    protected function evaluateAlerts(MonitoringTarget $t, ProbeResult $r, int $fails): void
    {
        $this->toggleAlert($t, 'down', 'critical', $fails >= $t->down_after_failures, "{$t->name} tidak terjangkau ({$fails}× berturut-turut)", $fails);
        $this->toggleAlert($t, 'latency', 'warning', $r->latencyMs !== null && $r->latencyMs > $t->latency_warn_ms, "Latensi {$t->name} {$r->latencyMs} ms (batas {$t->latency_warn_ms} ms)", $r->latencyMs);
        $this->toggleAlert($t, 'loss', 'warning', $r->status === 'up' && $r->lossPct !== null && $r->lossPct >= $t->loss_warn_pct, "Packet loss {$t->name} {$r->lossPct}%", $r->lossPct);
        $this->toggleAlert($t, 'cpu', 'warning', $r->cpuPct !== null && $r->cpuPct >= 90, "CPU {$t->name} {$r->cpuPct}%", $r->cpuPct);
    }

    /** Dedupe: satu alarm terbuka per (target, metrik); otomatis pulih saat kondisi normal. */
    protected function toggleAlert(MonitoringTarget $t, string $metric, string $severity, bool $active, string $message, $value): void
    {
        $open = MonitoringAlert::where('monitoring_target_id', $t->id)->where('metric', $metric)->whereIn('status', ['open', 'acknowledged'])->first();
        if ($active && ! $open) {
            MonitoringAlert::create(['monitoring_target_id' => $t->id, 'metric' => $metric, 'severity' => $severity, 'status' => 'open', 'message' => $message, 'last_value' => $value, 'started_at' => now()]);
        } elseif ($active && $open) {
            $open->update(['last_value' => $value, 'message' => $message]);
        } elseif (! $active && $open) {
            $open->update(['status' => 'resolved', 'resolved_at' => now()]);
        }
    }
}
