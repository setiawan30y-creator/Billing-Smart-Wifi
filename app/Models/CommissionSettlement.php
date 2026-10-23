<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CommissionSettlement extends Model
{
    use BelongsToTenant;

    protected $table = 'commission_settlements';

    protected $fillable = ['number', 'user_id', 'total', 'entries_count', 'reference', 'notes', 'paid_at', 'created_by'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }
}
