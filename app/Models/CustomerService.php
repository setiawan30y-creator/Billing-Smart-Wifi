<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerService extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['active' => 'Aktif', 'isolated' => 'Terisolir', 'terminated' => 'Berhenti'];

    /** prepaid = tagih bulan berjalan (bayar di muka); postpaid = tagih pemakaian bulan sebelumnya. */
    public const BILLING_MODES = ['prepaid' => 'Prabayar', 'postpaid' => 'Pascabayar'];

    protected $fillable = [
        'customer_id', 'service_package_id', 'status', 'price_override', 'billing_day', 'due_days', 'billing_mode', 'pppoe_username', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return ['price_override' => 'decimal:2', 'started_at' => 'date', 'ended_at' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'service_package_id');
    }

    public function odpPort(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OdpPort::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isPostpaid(): bool
    {
        return $this->billing_mode === 'postpaid';
    }

    public function billingModeLabel(): string
    {
        return self::BILLING_MODES[$this->billing_mode ?? 'prepaid'] ?? 'Prabayar';
    }

    /** Periode pemakaian yang ditagih pada bulan penagihan $runPeriod (YYYY-MM). */
    public function billedPeriodFor(string $runPeriod): string
    {
        return $this->isPostpaid()
            ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $runPeriod)->subMonthNoOverflow()->format('Y-m')
            : $runPeriod;
    }

    public function monthlyPrice(): string
    {
        return (string) ($this->price_override ?? $this->package->price);
    }
}
