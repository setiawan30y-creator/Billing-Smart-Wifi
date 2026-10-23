<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Integrations\MikroTik\PppoeReader;
use App\Models\CustomerService;
use App\Models\NetworkDevice;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * PPPoE MikroTik: daftar PPP secret & sesi online, dicocokkan dengan layanan pelanggan (username PPPoE).
 * Read-only — tidak ada perubahan konfigurasi router dari halaman ini.
 * @2026-Alifiandra
 */
class PppoeController extends Controller
{
    public const CACHE_SECONDS = 30;

    public function index(Request $request, PppoeReader $reader)
    {
        $tab = $request->query('tab') === 'active' ? 'active' : 'secrets';
        $routers = NetworkDevice::where('type', 'mikrotik')->where('is_active', true)->orderBy('name')->get();
        $device = $routers->firstWhere('id', (int) $request->query('device')) ?? $routers->first();

        $view = ['tab' => $tab, 'routers' => $routers, 'device' => $device, 'error' => null, 'data' => null,
            'rows' => null, 'stats' => null, 'cachedAt' => null];
        if (! $device) {
            return view('isp.pppoe.index', $view);
        }

        $key = 'pppoe:'.$device->tenant_id.':'.$device->id;
        if ($request->boolean('refresh')) {
            Cache::forget($key);
        }
        try {
            $data = Cache::remember($key, self::CACHE_SECONDS, fn () => $reader->read($device));
        } catch (Throwable $e) {
            $view['error'] = mb_substr($e->getMessage(), 0, 250);

            return view('isp.pppoe.index', $view);
        }

        // Layanan pelanggan berdasarkan username PPPoE (tenant-scoped).
        $services = CustomerService::with(['customer:id,name,customer_code', 'package:id,name'])
            ->whereNotNull('pppoe_username')->where('pppoe_username', '!=', '')
            ->where('status', '!=', 'terminated')->get()->keyBy('pppoe_username');

        $activeByName = collect($data['active'])->keyBy('name');
        $secretNames = collect($data['secrets'])->pluck('name')->flip();

        $secrets = collect($data['secrets'])->map(fn ($s) => $s + [
            'session' => $activeByName->get($s['name']),
            'service_row' => $services->get($s['name']),
        ]);
        $active = collect($data['active'])->map(fn ($a) => $a + [
            'has_secret' => $secretNames->has($a['name']),
            'service_row' => $services->get($a['name']),
        ]);

        $stats = [
            'secrets' => $secrets->count(),
            'disabled' => $secrets->where('disabled', true)->count(),
            'online' => $active->count(),
            'offline' => $secrets->filter(fn ($s) => ! $s['disabled'] && ! $s['session'])->count(),
            'unlinked' => $secrets->filter(fn ($s) => ! $s['service_row'])->count(),
            'isolated_online' => $active->filter(fn ($a) => $a['service_row']?->status === 'isolated')->count(),
        ];

        $q = mb_strtolower(trim((string) $request->query('q')));
        $filter = (string) $request->query('filter');
        $rows = ($tab === 'active' ? $active : $secrets)
            ->filter(function ($r) use ($q, $filter, $tab) {
                if ($q !== '') {
                    $hay = mb_strtolower(implode(' ', [$r['name'], $r['comment'] ?? '', $r['remote_address'] ?? '', $r['address'] ?? '',
                        $r['caller_id'] ?? '', $r['last_caller_id'] ?? '', $r['profile'] ?? '',
                        $r['service_row']?->customer?->name ?? '', $r['service_row']?->customer?->customer_code ?? '']));
                    if (! str_contains($hay, $q)) {
                        return false;
                    }
                }

                return match ($filter) {
                    'online' => $tab === 'secrets' && $r['session'] !== null,
                    'offline' => $tab === 'secrets' && ! $r['disabled'] && $r['session'] === null,
                    'disabled' => $tab === 'secrets' && $r['disabled'],
                    'unlinked' => $r['service_row'] === null,
                    'isolated' => $r['service_row']?->status === 'isolated',
                    default => true,
                };
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $perPage = 50;
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('isp.pppoe.index', array_merge($view, [
            'rows' => $paginator, 'stats' => $stats, 'cachedAt' => $data['fetched_at'],
        ]));
    }
}
