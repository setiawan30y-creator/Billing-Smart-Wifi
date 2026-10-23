<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    public const STATUSES = [
        'trial' => 'Trial', 'active' => 'Aktif', 'past_due' => 'Lewat jatuh tempo', 'cancelled' => 'Dibatalkan', 'ended' => 'Berakhir',
    ];

    protected $fillable = [
        'saas_plan_id', 'period', 'status', 'price', 'client_quota', 'router_quota',
        'overage_client_price', 'overage_router_price', 'starts_at', 'ends_at', 'trial_ends_at', 'grace_days',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'overage_client_price' => 'decimal:2',
            'overage_router_price' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'trial_ends_at' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    /** Snapshot harga & kuota dari katalog paket resmi (bukan input bebas). */
    public static function snapshotFromPlan(SaasPlan $plan, string $period): array
    {
        return [
            'saas_plan_id' => $plan->id,
            'period' => $period,
            'price' => $period === 'yearly' ? ($plan->price_yearly ?? null) : $plan->price_monthly,
            'client_quota' => $plan->client_quota,
            'router_quota' => $plan->router_quota,
            'overage_client_price' => $plan->overage_client_price,
            'overage_router_price' => $plan->overage_router_price,
        ];
    }
}
