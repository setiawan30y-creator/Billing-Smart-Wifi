<?php

namespace App\Integrations\Monitoring;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * ICMP ping memakai perintah sistem `ping` (Windows/Linux). Hasil diparse dari output.
 */
class IcmpProbe
{
    public function check(string $host, int $count = 4): ProbeResult
    {
        if (! preg_match('/^[A-Za-z0-9.\-:]+$/', $host)) {
            return new ProbeResult('icmp', 'error', error: 'Host tidak valid.');
        }
        $win = PHP_OS_FAMILY === 'Windows';
        $cmd = $win ? ['ping', '-n', (string) $count, '-w', '1000', $host] : ['ping', '-c', (string) $count, '-W', '1', $host];

        try {
            $res = Process::timeout($count * 2 + 5)->run($cmd);
        } catch (Throwable $e) {
            return new ProbeResult('icmp', 'error', error: 'Perintah ping tidak dapat dijalankan: '.mb_substr($e->getMessage(), 0, 120));
        }
        $out = $res->output().$res->errorOutput();

        return self::parse($out);
    }

    public static function parse(string $out): ProbeResult
    {
        if (! preg_match('/(\d+(?:\.\d+)?)%/', $out, $m)) {
            return new ProbeResult('icmp', 'error', error: 'Output ping tidak dikenali: '.mb_substr(trim($out), 0, 120));
        }
        $loss = (float) $m[1];
        $avg = null;
        if (preg_match('/=\s*[\d.]+\/([\d.]+)\/[\d.]+/', $out, $a)) {          // Linux: rtt min/avg/max
            $avg = (float) $a[1];
        } elseif (preg_match_all('/=\s*(\d+)\s*ms/i', $out, $a) && $a[1]) {   // Windows: ... Average = 12ms (nilai terakhir)
            $avg = (float) end($a[1]);
        }

        return new ProbeResult('icmp', $loss >= 100 ? 'down' : 'up', $loss >= 100 ? null : $avg, $loss);
    }
}
