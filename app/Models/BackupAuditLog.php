<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'action', 'subject_id', 'user_id', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
