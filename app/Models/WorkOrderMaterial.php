<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderMaterial extends Model
{
    protected $fillable = ['work_order_id', 'inventory_item_id', 'inventory_location_id', 'quantity', 'inventory_serial_number_id', 'inventory_movement_id', 'returned', 'used_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'returned' => 'boolean'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerialNumber::class, 'inventory_serial_number_id');
    }
}
