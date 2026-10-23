<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPlan extends Model
{
    public const FEATURES = [
        'billing' => 'Billing & invoice pelanggan',
        'payments_manual' => 'Pembayaran manual & verifikasi',
        'payment_gateway' => 'Integrasi Payment Gateway',
        'whatsapp' => 'Notifikasi WhatsApp',
        'network_registry' => 'Registry router & perangkat',
        'monitoring' => 'Monitoring jaringan',
        'custom_domain' => 'Domain custom',
        'theme_studio' => 'Custom Theme Studio',
        'api_access' => 'Akses API',
    ];

    protected $fillable = [
        'code', 'name', 'description', 'price_monthly', 'price_yearly', 'client_quota', 'router_quota',
        'overage_client_price', 'overage_router_price', 'features', 'is_custom', 'is_active', 'is_price_approved', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'overage_client_price' => 'decimal:2',
            'overage_router_price' => 'decimal:2',
            'features' => 'array',
            'is_custom' => 'boolean',
            'is_active' => 'boolean',
            'is_price_approved' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}
