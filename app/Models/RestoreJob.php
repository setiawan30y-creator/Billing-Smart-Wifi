<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestoreJob extends Model
{
    protected $table = 'restore_jobs';

    protected $fillable = ['tenant_id', 'backup_artifact_id', 'mode', 'status', 'report', 'reason', 'requested_by', 'started_at', 'finished_at'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['report' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function requester(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
