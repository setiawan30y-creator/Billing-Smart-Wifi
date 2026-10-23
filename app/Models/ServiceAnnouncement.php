<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceAnnouncement extends Model
{
    use BelongsToTenant;

    public const MODES = ['all' => 'Semua pelanggan aktif', 'incident' => 'Pelanggan terdampak insiden', 'odp' => 'Pelanggan di ODP tertentu', 'odc' => 'Pelanggan di ODC tertentu', 'device' => 'Pelanggan di OLT/perangkat tertentu', 'package' => 'Pelanggan paket tertentu'];

    protected $fillable = ['network_incident_id', 'title', 'body', 'target_mode', 'send_whatsapp', 'show_in_portal', 'visible_until', 'status', 'recipient_count', 'sent_at', 'created_by', 'sent_by'];

    protected function casts(): array
    {
        return ['send_whatsapp' => 'boolean', 'show_in_portal' => 'boolean', 'visible_until' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AnnouncementDelivery::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(NetworkIncident::class, 'network_incident_id');
    }

    /** Pengumuman terkirim yang tampil di portal untuk pelanggan ini. */
    public function scopeVisibleToCustomer($q, int $customerId)
    {
        return $q->where('status', 'sent')->where('show_in_portal', true)
            ->where(fn ($w) => $w->whereNull('visible_until')->orWhere('visible_until', '>', now()))
            ->whereHas('deliveries', fn ($d) => $d->where('customer_id', $customerId));
    }
}
