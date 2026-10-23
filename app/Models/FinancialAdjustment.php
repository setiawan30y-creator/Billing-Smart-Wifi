<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FinancialAdjustment extends Model
{
    use BelongsToTenant;

    protected $table = 'financial_adjustments';

    protected $fillable = ['number', 'customer_id', 'invoice_id', 'type', 'amount', 'reason', 'status', 'created_by', 'approved_by', 'approved_at', 'review_note'];

    public const TYPES = ['credit_note' => 'Credit note (potongan)', 'write_off' => 'Penghapusan piutang'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
