<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const STATUSES = ['prospect' => 'Calon pelanggan', 'active' => 'Aktif', 'inactive' => 'Nonaktif'];

    protected $fillable = [
        'customer_code', 'name', 'phone', 'email', 'address', 'landmark', 'maps_url', 'latitude', 'longitude',
        'ownership', 'owner_name', 'status', 'notes',
    ];

    protected $hidden = ['nik'];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(CustomerService::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function setNik(?string $nik): void
    {
        $nik = $nik ? preg_replace('/\D/', '', $nik) : null;
        $this->nik = $nik ?: null;
        $this->nik_last4 = $nik ? substr($nik, -4) : null;
    }

    public function maskedNik(): string
    {
        return $this->nik_last4 ? '************'.$this->nik_last4 : '-';
    }

    public function outstanding(): string
    {
        $rows = $this->invoices()->whereIn('status', ['unpaid', 'partial'])->get(['id', 'total', 'amount_paid', 'adjusted_amount']);

        return \App\Support\Money::add(...$rows->map(fn ($i) => $i->balance())->all());
    }
}
