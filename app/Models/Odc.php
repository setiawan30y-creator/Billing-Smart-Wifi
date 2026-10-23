<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Odc extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['network_device_id', 'code', 'name', 'capacity', 'address', 'latitude', 'longitude', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'network_device_id')->withTrashed();
    }

    public function odps(): HasMany
    {
        return $this->hasMany(Odp::class);
    }
}
