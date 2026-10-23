<?php

namespace App\Services;

use App\Integrations\MikroTik\PppoeReader;
use App\Models\CustomerService;
use App\Models\NetworkDevice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Status online/offline pelanggan PPPoE dari semua router MikroTik tenant (read-only, cache singkat).
 * @2026-Alifiandra
 */
class PppoeStatusService
{
    public const CACHE_SECONDS = 30;

    public function __construct(protected PppoeReader $reader) {}

    public static function cacheKey(NetworkDevice $device): string
    {
        return 'pppoe:'.$device->tenant_id.':'.$device->id;
    }

    /** Data satu router (secret + active), dari cache bila masih segar. */
    public function fetch(NetworkDevice $device, bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::cacheKey($device));
        }

        return Cache::remember(self::cacheKey($device), self::CACHE_SECONDS, fn () => $this->reader->read($device));
    }

    /** Router MikroTik aktif yang punya kredensial API. */
    public function routers(): Collection
    {
        return NetworkDevice::where('type', 'mikrotik')->where('is_active', true)->whereNotNull('username')->orderBy('name')->get();
    }

    /**
     * Ringkasan status pelanggan: online bila username PPPoE layanan ada di sesi aktif salah satu router.
     * Bila ada router yang gagal dibaca, pelanggan yang tidak ditemukan online dihitung "tidak diketahui"
     * (bukan offline) agar angka tidak menyesatkan.
     */
    public function customerSummary(): array
    {
        $routers = $this->routers();
        $sessions = collect();
        $errors = [];
        $fetchedAt = null;
        foreach ($routers as $r) {
            try {
                $data = $this->fetch($r);
                foreach ($data['active'] as $a) {
                    $sessions->put($a['name'], $a + ['router' => $r->name]);
                }
                $fetchedAt = max($fetchedAt ?? $data['fetched_at'], $data['fetched_at']);
            } catch (Throwable $e) {
                $errors[] = ['router' => $r->name, 'message' => mb_substr($e->getMessage(), 0, 160)];
            }
        }

        $services = CustomerService::with(['customer:id,name,customer_code,phone', 'package:id,name'])
            ->whereIn('status', ['active', 'isolated'])
            ->whereNotNull('pppoe_username')->where('pppoe_username', '!=', '')->get();

        [$online, $offline, $isolatedOnline] = [collect(), collect(), collect()];
        foreach ($services as $s) {
            $session = $sessions->get($s->pppoe_username);
            $row = ['service' => $s, 'session' => $session];
            if ($session) {
                $online->push($row);
                if ($s->status === 'isolated') {
                    $isolatedOnline->push($row);
                }
            } elseif ($s->status === 'active') {
                $offline->push($row);
            }
        }
        $unknown = $errors ? $offline->count() : 0;

        return [
            'routers' => $routers->count(),
            'errors' => $errors,
            'fetched_at' => $fetchedAt,
            'tracked' => $services->count(),
            'without_username' => CustomerService::where('status', 'active')
                ->where(fn ($q) => $q->whereNull('pppoe_username')->orWhere('pppoe_username', ''))->count(),
            'online' => $online->count(),
            'offline' => $errors ? 0 : $offline->count(),
            'unknown' => $unknown,
            'isolated_online' => $isolatedOnline->count(),
            'offline_list' => $errors ? collect() : $offline->sortBy(fn ($r) => $r['service']->customer?->name)->values(),
            'isolated_list' => $isolatedOnline->values(),
            // service_id => online|offline (offline hanya bila semua router terbaca)
            'states' => $online->mapWithKeys(fn ($r) => [$r['service']->id => 'online'])
                ->union($errors ? [] : $offline->mapWithKeys(fn ($r) => [$r['service']->id => 'offline']))->all(),
        ];
    }
}
