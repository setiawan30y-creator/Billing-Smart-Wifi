<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $fillable = ['import_batch_id', 'row_number', 'data', 'normalized', 'status', 'errors', 'warnings', 'customer_id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'normalized' => 'array', 'errors' => 'array', 'warnings' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
