<?php

namespace App\Integrations\Monitoring;

/**
 * Hasil satu pengukuran. status:
 *  - up    : perangkat menjawab
 *  - down  : pengukuran berhasil dijalankan, tetapi perangkat tidak menjawab
 *  - error : collector gagal mengukur (mis. tool tidak ada / kredensial salah) — BUKAN bukti perangkat down
 * Nilai null berarti tidak diukur (tidak boleh ditampilkan seolah-olah terukur).
 */
class ProbeResult
{
    public function __construct(
        public string $source,
        public string $status,
        public ?float $latencyMs = null,
        public ?float $lossPct = null,
        public ?float $cpuPct = null,
        public ?float $memoryPct = null,
        public ?int $uptimeSeconds = null,
        public ?int $rxBps = null,
        public ?int $txBps = null,
        public ?int $pppoeActive = null,
        public ?string $error = null,
    ) {}
}
