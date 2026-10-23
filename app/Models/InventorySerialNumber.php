<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySerialNumber extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['in_stock' => 'Di stok', 'installed' => 'Terpasang', 'faulty' => 'Rusak', 'returned' => 'Dikembalikan'];

    protected $fillable = ['inventory_item_id', 'serial', 'status', 'inventory_location_id', 'customer_service_id'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class, 'customer_service_id');
    }
}
