<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringCollectionRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['tenant_id', 'started_at', 'finished_at', 'targets', 'up', 'down', 'collector_errors', 'error'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
