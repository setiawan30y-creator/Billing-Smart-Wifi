<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringTarget extends Model
{
    use BelongsToTenant;

    public const METHODS = [
        'tcp' => 'TCP connect (latensi koneksi ke port)',
        'icmp' => 'ICMP ping (latensi & packet loss)',
        'routeros' => 'MikroTik RouterOS API read-only (CPU, RAM, uptime, trafik, PPPoE)',
    ];

    public const STATUSES = ['up' => 'Normal', 'degraded' => 'Menurun', 'down' => 'Tidak terjangkau', 'unknown' => 'Belum ada data'];

    protected $fillable = ['network_device_id', 'name', 'host', 'method', 'port', 'interface_name', 'enabled', 'interval_minutes', 'latency_warn_ms', 'loss_warn_pct', 'down_after_failures'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_checked_at' => 'datetime', 'last_latency_ms' => 'decimal:2', 'last_loss_pct' => 'decimal:2'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'network_device_id')->withTrashed();
    }

    public function samples(): HasMany
    {
        return $this->hasMany(MonitoringSample::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(MonitoringAlert::class);
    }

    public function isDue(): bool
    {
        return ! $this->last_checked_at || $this->last_checked_at->lte(now()->subMinutes($this->interval_minutes)->addSeconds(20));
    }

    /** Data dianggap basi bila lebih dari 3× interval tanpa sampel. */
    public function isStale(): bool
    {
        return $this->enabled && (! $this->last_checked_at || $this->last_checked_at->lt(now()->subMinutes($this->interval_minutes * 3)));
    }

    public function statusBadge(): string
    {
        return ['up' => 'active', 'degraded' => 'pending', 'down' => 'failed', 'unknown' => 'muted'][$this->last_status] ?? 'muted';
    }
}
