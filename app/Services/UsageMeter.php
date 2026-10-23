<?php

namespace App\Services;

use App\Models\SaasUsageRecord;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Penghitungan pemakaian untuk kuota SaaS.
 * Client aktif = pelanggan UNIK dengan minimal satu layanan berstatus active/isolated
 * (layanan terminated & pelanggan terarsip tidak dihitung). Jumlah layanan dicatat terpisah.
 */
class UsageMeter
{
    public const COUNTED_SERVICE_STATUSES = ['active', 'isolated'];

    public static function current(int $tenantId): array
    {
        $base = DB::table('customer_services')
            ->join('customers', 'customers.id', '=', 'customer_services.customer_id')
            ->where('customer_services.tenant_id', $tenantId)
            ->whereNull('customers.deleted_at')
            ->whereIn('customer_services.status', self::COUNTED_SERVICE_STATUSES);

        return [
            'active_clients' => (clone $base)->distinct()->count('customer_services.customer_id'),
            'active_services' => (clone $base)->count('customer_services.id'),
            'routers' => DB::table('network_devices')->where('tenant_id', $tenantId)
                ->where('type', 'mikrotik')->whereNull('deleted_at')->count(),
        ];
    }

    public static function snapshot(Tenant $tenant, string $period): SaasUsageRecord
    {
        $usage = self::current($tenant->id);

        return SaasUsageRecord::create([
            'tenant_id' => $tenant->id,
            'tenant_subscription_id' => $tenant->currentSubscription?->id,
            'period' => $period,
            'measured_at' => now(),
        ] + $usage);
    }

    /** Status kuota untuk ditampilkan & peringatan. */
    public static function quotaStatus(Tenant $tenant): array
    {
        $sub = $tenant->currentSubscription;
        $usage = self::current($tenant->id);

        $calc = function (?int $quota, int $used) {
            if ($quota === null) {
                return ['quota' => null, 'used' => $used, 'percent' => null, 'over' => 0, 'level' => 'ok'];
            }
            $pct = $quota > 0 ? (int) floor($used * 100 / $quota) : 100;

            return [
                'quota' => $quota, 'used' => $used, 'percent' => $pct, 'over' => max(0, $used - $quota),
                'level' => $used > $quota ? 'over' : ($pct >= 90 ? 'warn' : 'ok'),
            ];
        };

        return [
            'clients' => $calc($sub?->client_quota, $usage['active_clients']),
            'routers' => $calc($sub?->router_quota, $usage['routers']),
            'services' => $usage['active_services'],
        ];
    }
}
