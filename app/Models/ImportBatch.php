<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['validated' => 'Siap diimport', 'processing' => 'Diproses', 'completed' => 'Selesai', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan'];

    protected $fillable = ['type', 'original_name', 'file_path', 'status', 'total_rows', 'valid_rows', 'error_rows', 'imported_rows', 'failed_rows', 'created_by', 'executed_by', 'executed_at', 'completed_at', 'message'];

    protected function casts(): array
    {
        return ['executed_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('row_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
