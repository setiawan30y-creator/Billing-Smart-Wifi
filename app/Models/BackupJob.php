<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupJob extends Model
{
    protected $table = 'backup_jobs';

    protected $fillable = ['tenant_id', 'trigger', 'status', 'started_at', 'finished_at', 'error', 'created_by'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function artifact(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BackupArtifact::class);
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
