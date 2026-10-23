<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    use BelongsToTenant;

    protected $table = 'commission_rules';

    protected $fillable = ['name', 'user_id', 'type', 'value', 'method', 'is_active', 'effective_from'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'is_active' => 'boolean', 'effective_from' => 'date'];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
