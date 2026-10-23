<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CommissionEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'commission_entries';

    protected $fillable = ['payment_id', 'user_id', 'commission_rule_id', 'kind', 'base_amount', 'amount', 'status', 'commission_settlement_id'];

    protected function casts(): array
    {
        return ['base_amount' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
