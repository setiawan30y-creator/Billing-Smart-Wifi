<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain baru dianggap aktif hanya setelah status "verified" (DNS + TLS dicek),
 * bukan sekadar diisi di form.
 */
class TenantDomain extends Model
{
    protected $fillable = ['domain', 'type', 'status', 'verification_token'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
