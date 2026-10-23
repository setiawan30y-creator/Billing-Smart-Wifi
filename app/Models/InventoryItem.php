<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use BelongsToTenant;

    public const CATEGORIES = ['router' => 'Router', 'ont' => 'ONT/ONU', 'cable' => 'Kabel', 'splitter' => 'Splitter', 'connector' => 'Konektor', 'odp' => 'Perangkat ODP', 'tool' => 'Alat kerja', 'other' => 'Lainnya'];

    public const UNITS = ['pcs' => 'pcs', 'meter' => 'meter', 'roll' => 'roll', 'box' => 'box'];

    protected $fillable = ['sku', 'name', 'category', 'unit', 'track_serial', 'min_stock', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['track_serial' => 'boolean', 'is_active' => 'boolean', 'min_stock' => 'decimal:2'];
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(InventorySerialNumber::class);
    }

    public function totalStock(): string
    {
        return \App\Support\Money::add(...$this->balances->pluck('quantity')->all());
    }

    public static function qty($v): string
    {
        $s = rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');

        return $s === '' ? '0' : $s;
    }
}
