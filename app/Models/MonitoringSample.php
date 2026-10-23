<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MonitoringSample extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['monitoring_target_id', 'measured_at', 'source', 'status', 'latency_ms', 'packet_loss_pct', 'cpu_load_pct', 'memory_used_pct', 'uptime_seconds', 'rx_bps', 'tx_bps', 'pppoe_active', 'error'];

    protected function casts(): array
    {
        return ['measured_at' => 'datetime'];
    }
}
