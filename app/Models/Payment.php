<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak', 'reversed' => 'Dibalik (reversal)'];

    public const METHODS = ['cash' => 'Tunai', 'transfer' => 'Transfer bank', 'gateway' => 'Payment Gateway', 'other' => 'Lainnya'];

    protected $fillable = [
        'customer_id', 'number', 'amount', 'unallocated_amount', 'method', 'reference', 'status', 'proof_path', 'paid_at',
        'received_by', 'idempotency_key', 'planned_invoice_ids', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'unallocated_amount' => 'decimal:2',
            'planned_invoice_ids' => 'array',
            'paid_at' => 'datetime', 'verified_at' => 'datetime', 'reversed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
