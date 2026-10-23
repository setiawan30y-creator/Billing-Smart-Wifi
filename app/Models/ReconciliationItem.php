<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationItem extends Model
{
    protected $fillable = ['reconciliation_batch_id', 'row_number', 'txn_date', 'description', 'amount', 'reference', 'status', 'payment_id', 'match_type', 'note', 'resolved_by'];

    protected function casts(): array
    {
        return ['txn_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
