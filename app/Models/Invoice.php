<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['unpaid' => 'Belum bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', 'void' => 'Dibatalkan'];

    protected $fillable = [
        'customer_id', 'customer_service_id', 'number', 'period', 'issue_date', 'due_date', 'subtotal', 'discount', 'tax',
        'total', 'amount_paid', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'due_date' => 'date',
            'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'amount_paid' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class, 'customer_service_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')->withPivot('amount')->withTimestamps();
    }

    public function balance(): string
    {
        return Money::sub($this->total, $this->amount_paid);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['unpaid', 'partial'], true) && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    /**
     * Hitung ulang amount_paid & status dari alokasi pembayaran TERVERIFIKASI saja.
     */
    public function recalculate(): void
    {
        $paid = Money::add(...$this->allocations()
            ->whereHas('payment', fn ($q) => $q->where('status', 'verified'))
            ->pluck('amount')->all());

        $this->amount_paid = $paid;

        if ($this->status !== 'void') {
            $this->status = Money::cmp($paid, $this->total) >= 0 ? 'paid'
                : (Money::isPositive($paid) ? 'partial' : 'unpaid');
        }

        $this->save();
    }
}
