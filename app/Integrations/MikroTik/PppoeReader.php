<?php

namespace App\Integrations\MikroTik;

use App\Integrations\Monitoring\RouterOsClient;
use App\Models\NetworkDevice;
use RuntimeException;

/**
 * Membaca PPP secret & sesi PPPoE aktif dari MikroTik via RouterOS API — READ-ONLY.
 * Password secret TIDAK pernah diminta dari router (.proplist tanpa "password").
 * @2026-Alifiandra
 */
class PppoeReader
{
    public const SECRET_FIELDS = ['.id', 'name', 'service', 'profile', 'local-address', 'remote-address', 'disabled', 'comment',
        'last-logged-out', 'last-caller-id', 'last-disconnect-reason'];

    public const ACTIVE_FIELDS = ['.id', 'name', 'service', 'address', 'caller-id', 'uptime', 'encoding', 'comment'];

    /**
     * @return array{secrets: array<int, array<string, mixed>>, active: array<int, array<string, mixed>>, fetched_at: string}
     */
    public function read(NetworkDevice $device): array
    {
        if ($device->type !== 'mikrotik') {
            throw new RuntimeException('Perangkat ini bukan MikroTik.');
        }
        if (! $device->username) {
            throw new RuntimeException('Username API router belum diisi. Lengkapi di menu Router & Perangkat → Ubah.');
        }

        $api = $this->client();
        try {
            $api->connect($device->host, (int) ($device->api_port ?: 8728), $device->username, (string) $device->password, 6.0);

            $secrets = $api->command('/ppp/secret/print', ['.proplist' => implode(',', self::SECRET_FIELDS)]);
            if (! $secrets['done']) {
                throw new RuntimeException('Router menolak membaca PPP secret: '.$secrets['trap']);
            }
            $active = $api->command('/ppp/active/print', ['.proplist' => implode(',', self::ACTIVE_FIELDS)]);
            if (! $active['done']) {
                throw new RuntimeException('Router menolak membaca PPP active: '.$active['trap']);
            }
        } finally {
            $api->close();
        }

        return [
            'secrets' => array_map(fn ($r) => [
                'id' => $r['.id'] ?? null,
                'name' => $r['name'] ?? '',
                'service' => $r['service'] ?? 'any',
                'profile' => $r['profile'] ?? null,
                'local_address' => $r['local-address'] ?? null,
                'remote_address' => $r['remote-address'] ?? null,
                'disabled' => ($r['disabled'] ?? 'false') === 'true',
                'comment' => $r['comment'] ?? null,
                'last_logged_out' => $r['last-logged-out'] ?? null,
                'last_caller_id' => $r['last-caller-id'] ?? null,
                'last_disconnect_reason' => $r['last-disconnect-reason'] ?? null,
            ], $secrets['rows']),
            'active' => array_map(fn ($r) => [
                'id' => $r['.id'] ?? null,
                'name' => $r['name'] ?? '',
                'service' => $r['service'] ?? null,
                'address' => $r['address'] ?? null,
                'caller_id' => $r['caller-id'] ?? null,
                'uptime' => $r['uptime'] ?? null,
                'encoding' => $r['encoding'] ?? null,
                'comment' => $r['comment'] ?? null,
            ], $active['rows']),
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    protected function client(): RouterOsClient
    {
        return new RouterOsClient;
    }

    /** Detik → "2h 03j 04m" (hari, jam, menit). */
    public static function humanUptime(?string $uptime): string
    {
        if (! $uptime) {
            return '-';
        }
        $sec = \App\Integrations\Monitoring\RouterOsProbe::parseUptime($uptime);
        if ($sec === null) {
            return $uptime;
        }
        $d = intdiv($sec, 86400);
        $h = intdiv($sec % 86400, 3600);
        $m = intdiv($sec % 3600, 60);

        return $d > 0 ? sprintf('%dh %02dj %02dm', $d, $h, $m) : ($h > 0 ? sprintf('%dj %02dm', $h, $m) : sprintf('%dm %02dd', $m, $sec % 60));
    }
}
