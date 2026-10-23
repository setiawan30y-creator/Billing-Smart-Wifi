<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryMovement extends Model
{
    use BelongsToTenant;

    public const TYPES = ['receive' => 'Penerimaan', 'transfer' => 'Perpindahan', 'issue' => 'Pemakaian', 'return' => 'Pengembalian', 'adjust' => 'Penyesuaian'];

    protected $fillable = ['number', 'type', 'inventory_item_id', 'from_location_id', 'to_location_id', 'quantity', 'inventory_serial_number_id', 'work_order_id', 'reason', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Mutasi stok tidak boleh diubah. Gunakan penyesuaian.'));
        static::deleting(fn () => throw new LogicException('Mutasi stok tidak boleh dihapus.'));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerialNumber::class, 'inventory_serial_number_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
