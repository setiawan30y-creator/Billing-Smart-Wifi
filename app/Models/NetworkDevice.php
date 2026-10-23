<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkDevice extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const TYPES = ['mikrotik' => 'MikroTik', 'olt' => 'OLT', 'switch' => 'Switch', 'other' => 'Lainnya'];

    protected $fillable = ['name', 'type', 'host', 'api_port', 'username', 'location', 'latitude', 'longitude', 'is_active', 'notes'];

    protected $hidden = ['password'];

    public function monitoringTargets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MonitoringTarget::class);
    }

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }
}
