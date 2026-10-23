<?php

namespace App\Integrations\Monitoring;

use App\Models\NetworkDevice;
use Throwable;

/**
 * Pengukuran read-only via RouterOS API: CPU, RAM, uptime, trafik interface (sesaat), jumlah PPPoE aktif.
 * Latensi diukur dari waktu koneksi TCP ke port API.
 */
class RouterOsProbe
{
    public function __construct(protected TcpProbe $tcp) {}

    public function check(NetworkDevice $device, ?string $interface = null): ProbeResult
    {
        $port = $device->api_port ?: 8728;
        $reach = $this->tcp->check($device->host, $port, 2);
        if ($reach->status !== 'up') {
            return new ProbeResult('routeros', $reach->status, null, $reach->lossPct, error: $reach->error);
        }
        if (! $device->username) {
            return new ProbeResult('routeros', 'up', $reach->latencyMs, $reach->lossPct, error: 'Username API belum diisi — hanya keterjangkauan yang diukur.');
        }

        $api = new RouterOsClient;
        try {
            $api->connect($device->host, $port, $device->username, (string) $device->password);

            $res = $api->command('/system/resource/print')['rows'][0] ?? [];
            $cpu = isset($res['cpu-load']) ? (float) $res['cpu-load'] : null;
            $mem = (isset($res['total-memory'], $res['free-memory']) && (float) $res['total-memory'] > 0)
                ? round((1 - (float) $res['free-memory'] / (float) $res['total-memory']) * 100, 2) : null;
            $uptime = isset($res['uptime']) ? self::parseUptime($res['uptime']) : null;

            $rx = $tx = null;
            if ($interface) {
                $t = $api->command('/interface/monitor-traffic', ['interface' => $interface, 'once' => ''])['rows'][0] ?? [];
                $rx = isset($t['rx-bits-per-second']) ? (int) $t['rx-bits-per-second'] : null;
                $tx = isset($t['tx-bits-per-second']) ? (int) $t['tx-bits-per-second'] : null;
            }

            $ppp = $api->command('/ppp/active/print', ['count-only' => '']);
            $pppoe = $ppp['done'] && isset($ppp['ret']) ? (int) $ppp['ret'] : null;

            return new ProbeResult('routeros', 'up', $reach->latencyMs, $reach->lossPct, $cpu, $mem, $uptime, $rx, $tx, $pppoe);
        } catch (Throwable $e) {
            // Perangkat terjangkau, tetapi API gagal → error collector, bukan perangkat down.
            return new ProbeResult('routeros', 'error', $reach->latencyMs, $reach->lossPct, error: mb_substr($e->getMessage(), 0, 190));
        } finally {
            $api->close();
        }
    }

    /** "1w2d03:04:05" / "3d4h5m6s" → detik */
    public static function parseUptime(string $s): ?int
    {
        $total = 0;
        if (preg_match('/(\d+)w/', $s, $m)) {
            $total += $m[1] * 604800;
        }
        if (preg_match('/(\d+)d/', $s, $m)) {
            $total += $m[1] * 86400;
        }
        if (preg_match('/(\d+):(\d+):(\d+)/', $s, $m)) {
            $total += $m[1] * 3600 + $m[2] * 60 + $m[3];
        } else {
            foreach (['h' => 3600, 'm' => 60, 's' => 1] as $u => $mul) {
                if (preg_match('/(\d+)'.$u.'/', $s, $m)) {
                    $total += $m[1] * $mul;
                }
            }
        }

        return $total ?: null;
    }
}
