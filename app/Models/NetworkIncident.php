<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkIncident extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['investigating' => 'Investigasi', 'identified' => 'Penyebab ditemukan', 'monitoring' => 'Pemantauan pemulihan', 'resolved' => 'Selesai'];

    public const SEVERITIES = ['minor' => 'Minor', 'major' => 'Mayor', 'critical' => 'Kritis'];

    protected $fillable = ['number', 'title', 'description', 'severity', 'status', 'confirmed_cause', 'auto_detected', 'detection_reason', 'started_at', 'estimated_recovery_at', 'resolved_at', 'created_by'];

    protected function casts(): array
    {
        return ['auto_detected' => 'boolean', 'started_at' => 'datetime', 'estimated_recovery_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(IncidentDevice::class);
    }

    public function affectedCustomers(): HasMany
    {
        return $this->hasMany(IncidentAffectedCustomer::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class)->latest('id');
    }

    public function statusBadge(): string
    {
        return ['investigating' => 'failed', 'identified' => 'pending', 'monitoring' => 'running', 'resolved' => 'done'][$this->status] ?? 'muted';
    }
}
