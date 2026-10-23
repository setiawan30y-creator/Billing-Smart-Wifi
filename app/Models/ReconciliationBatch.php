<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReconciliationBatch extends Model
{
    use BelongsToTenant;

    protected $table = 'reconciliation_batches';

    protected $fillable = ['number', 'source', 'original_name', 'file_path', 'status', 'total_items', 'total_amount', 'created_by', 'completed_by', 'completed_at'];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReconciliationItem::class)->orderBy('row_number');
    }
}
