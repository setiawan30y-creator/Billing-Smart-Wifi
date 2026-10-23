<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupArtifact extends Model
{
    protected $table = 'backup_artifacts';

    protected $fillable = ['tenant_id', 'backup_job_id', 'path', 'size_bytes', 'checksum', 'table_counts', 'expires_at'];

    protected function casts(): array
    {
        return ['table_counts' => 'array', 'expires_at' => 'datetime'];
    }

    public function job(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BackupJob::class, 'backup_job_id');
    }

    public function restores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RestoreJob::class);
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
