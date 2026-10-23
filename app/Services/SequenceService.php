<?php

namespace App\Services;

use App\Models\TenantSequence;
use Illuminate\Support\Facades\DB;

/**
 * Nomor urut aman-konkurensi per tenant (lockForUpdate di dalam transaksi).
 */
class SequenceService
{
    public static function next(?int $tenantId, string $key): int
    {
        $tenantId = $tenantId ?? 0;

        return DB::transaction(function () use ($tenantId, $key) {
            DB::table('tenant_sequences')->insertOrIgnore(['tenant_id' => $tenantId, 'key' => $key, 'value' => 0]);
            $seq = TenantSequence::where('tenant_id', $tenantId)->where('key', $key)->lockForUpdate()->firstOrFail();

            $seq->value = $seq->value + 1;
            $seq->save();

            return $seq->value;
        });
    }

    public static function format(?int $tenantId, string $prefix, string $scope): string
    {
        $n = self::next($tenantId, $prefix.':'.$scope);

        return sprintf('%s/%s/%05d', $prefix, $scope, $n);
    }
}
