<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringAlert extends Model
{
    use BelongsToTenant;

    public const METRICS = ['down' => 'Tidak terjangkau', 'latency' => 'Latensi tinggi', 'loss' => 'Packet loss', 'cpu' => 'CPU tinggi'];

    protected $fillable = ['monitoring_target_id', 'metric', 'severity', 'status', 'message', 'last_value', 'started_at', 'resolved_at', 'acknowledged_by', 'acknowledged_at', 'ack_note'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'resolved_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(MonitoringTarget::class, 'monitoring_target_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
