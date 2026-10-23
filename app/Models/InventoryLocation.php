<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLocation extends Model
{
    use BelongsToTenant;

    public const TYPES = ['warehouse' => 'Gudang', 'vehicle' => 'Kendaraan', 'technician' => 'Stok teknisi'];

    protected $fillable = ['code', 'name', 'type', 'user_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
