<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new RuntimeException('Tidak ada konteks tenant saat membuat '.class_basename($model).'.');
            }

            // tenant_id selalu dari konteks server; nilai dari luar ditimpa.
            $model->tenant_id = $tenantId;
        });

        static::updating(function ($model) {
            if ($model->isDirty('tenant_id')) {
                throw new RuntimeException('tenant_id tidak boleh diubah.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
