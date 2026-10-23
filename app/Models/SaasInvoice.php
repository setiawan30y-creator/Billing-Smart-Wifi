<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasInvoice extends Model
{
    protected $fillable = [
        'tenant_id', 'tenant_subscription_id', 'saas_usage_record_id', 'number', 'period', 'issue_date', 'due_date',
        'subtotal', 'tax', 'total', 'amount_paid', 'status', 'is_simulation', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'due_date' => 'date',
            'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'amount_paid' => 'decimal:2',
            'is_simulation' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaasInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SaasPayment::class);
    }

    public function usage(): BelongsTo
    {
        return $this->belongsTo(SaasUsageRecord::class, 'saas_usage_record_id');
    }

    public function balance(): string
    {
        return Money::sub($this->total, $this->amount_paid);
    }

    public function recalculatePaid(): void
    {
        $paid = Money::add(...$this->payments()->where('status', 'verified')->pluck('amount')->all());
        $this->amount_paid = $paid;
        if ($this->status !== 'void') {
            $this->status = Money::cmp($paid, $this->total) >= 0 ? 'paid' : (Money::isPositive($paid) ? 'partial' : 'unpaid');
        }
        $this->save();
    }
}
