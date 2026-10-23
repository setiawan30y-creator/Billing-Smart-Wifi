<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Tenant extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'pending_activation' => 'Menunggu aktivasi',
        'provisioning' => 'Provisioning',
        'trial' => 'Trial',
        'active' => 'Aktif',
        'suspended' => 'Ditangguhkan',
        'provisioning_failed' => 'Provisioning gagal',
    ];

    /** Transisi status yang diizinkan (eksplisit & tervalidasi). */
    public const TRANSITIONS = [
        'draft' => ['pending_activation', 'provisioning'],
        'pending_activation' => ['provisioning', 'draft'],
        'provisioning' => ['trial', 'active', 'provisioning_failed', 'pending_activation'],
        'provisioning_failed' => ['provisioning'],
        'trial' => ['active', 'suspended'],
        'active' => ['suspended'],
        'suspended' => ['active', 'trial'],
    ];

    protected $fillable = ['code', 'name', 'subdomain', 'brand_name', 'theme', 'timezone', 'locale', 'settings'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(TenantProfile::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)
            ->whereIn('status', ['trial', 'active', 'past_due'])
            ->latestOfMany('starts_at');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function provisioningJobs(): HasMany
    {
        return $this->hasMany(TenantProvisioningJob::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function transitionTo(string $status): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new InvalidArgumentException("Transisi status {$this->status} → {$status} tidak diizinkan.");
        }

        $this->status = $status;
        if ($status === 'suspended') {
            $this->suspended_at = now();
        } elseif ($this->suspended_at) {
            $this->suspended_at = null;
            $this->suspended_reason = null;
        }
        $this->save();
    }

    public function isOperational(): bool
    {
        return in_array($this->status, ['trial', 'active'], true);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function displayName(): string
    {
        return $this->brand_name ?: $this->name;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
