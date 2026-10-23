<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProvisioningJob extends Model
{
    public const STEPS = [
        'config' => 'Konfigurasi awal tenant',
        'domain' => 'Status domain',
        'branding' => 'Branding',
        'admin_invitation' => 'Undangan administrator',
    ];

    protected $fillable = ['step', 'idempotency_key', 'status', 'attempts', 'last_error', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
