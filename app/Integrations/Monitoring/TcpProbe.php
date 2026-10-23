<?php

namespace App\Integrations\Monitoring;

/** Latensi koneksi TCP ke host:port (3 percobaan). Tidak butuh hak akses khusus. */
class TcpProbe
{
    public function check(string $host, int $port, int $attempts = 3, float $timeout = 2.0): ProbeResult
    {
        $times = [];
        $lastErr = null;
        for ($i = 0; $i < $attempts; $i++) {
            $t = microtime(true);
            $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if ($fp) {
                $times[] = (microtime(true) - $t) * 1000;
                fclose($fp);
            } else {
                $lastErr = trim($errstr) ?: 'koneksi gagal';
                if ($errno === 0 && $errstr === '' && ! filter_var($host, FILTER_VALIDATE_IP) && gethostbyname($host) === $host) {
                    return new ProbeResult('tcp', 'error', error: "Host {$host} tidak dapat di-resolve (DNS).");
                }
            }
        }
        $loss = round(($attempts - count($times)) * 100 / $attempts, 2);
        if (! $times) {
            return new ProbeResult('tcp', 'down', null, 100.0, error: "Port {$port}: {$lastErr}");
        }

        return new ProbeResult('tcp', 'up', round(array_sum($times) / count($times), 2), $loss);
    }
}
