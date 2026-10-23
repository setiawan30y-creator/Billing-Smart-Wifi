<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasUsageRecord extends Model
{
    protected $fillable = ['tenant_id', 'tenant_subscription_id', 'period', 'active_clients', 'active_services', 'routers', 'measured_at'];

    protected function casts(): array
    {
        return ['measured_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
