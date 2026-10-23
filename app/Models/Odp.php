<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Odp extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const STATUSES = ['active' => 'Aktif', 'maintenance' => 'Perbaikan', 'inactive' => 'Nonaktif'];

    public const SPLITTERS = ['1:2', '1:4', '1:8', '1:16', '1:32'];

    protected $fillable = [
        'odc_id', 'network_device_id', 'pon_port', 'code', 'name', 'splitter', 'capacity', 'address', 'landmark',
        'latitude', 'longitude', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function odc(): BelongsTo
    {
        return $this->belongsTo(Odc::class)->withTrashed();
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'network_device_id')->withTrashed();
    }

    public function ports(): HasMany
    {
        return $this->hasMany(OdpPort::class)->orderBy('port_number');
    }

    /** Tambah port yang belum ada sampai sejumlah kapasitas. */
    public function syncPorts(): void
    {
        $existing = $this->ports()->pluck('port_number')->all();
        for ($i = 1; $i <= $this->capacity; $i++) {
            if (! in_array($i, $existing, true)) {
                $this->ports()->create(['port_number' => $i, 'status' => 'available']);
            }
        }
    }

    public function usageLevel(int $used): string
    {
        if ($this->capacity < 1) {
            return 'muted';
        }
        $pct = $used * 100 / $this->capacity;

        return $pct >= 100 ? 'danger' : ($pct >= 75 ? 'warning' : 'success');
    }
}
