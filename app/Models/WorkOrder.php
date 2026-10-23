<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use BelongsToTenant;

    public const TYPES = ['installation' => 'Pemasangan baru', 'repair' => 'Perbaikan', 'visit' => 'Kunjungan', 'dismantle' => 'Pencabutan', 'other' => 'Lainnya'];

    public const STATUSES = ['open' => 'Baru', 'scheduled' => 'Terjadwal', 'in_progress' => 'Dikerjakan', 'on_hold' => 'Tertunda', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    public const TRANSITIONS = [
        'open' => ['scheduled', 'in_progress', 'cancelled'],
        'scheduled' => ['in_progress', 'on_hold', 'cancelled', 'open'],
        'in_progress' => ['on_hold', 'completed', 'cancelled'],
        'on_hold' => ['in_progress', 'scheduled', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public const DEFAULT_CHECKLIST = [
        'installation' => ['Survei titik & jalur kabel', 'Tarik kabel drop dari ODP', 'Pasang & konfigurasi ONT/router', 'Uji koneksi & kecepatan', 'Foto hasil pemasangan', 'Edukasi pelanggan (WiFi & password)'],
        'repair' => ['Cek sinyal/redaman di ODP', 'Cek kabel drop & konektor', 'Cek perangkat pelanggan', 'Uji koneksi setelah perbaikan', 'Foto hasil perbaikan'],
        'dismantle' => ['Cabut perangkat pelanggan', 'Lepas kabel drop dari ODP', 'Kembalikan perangkat ke gudang'],
    ];

    protected $fillable = ['number', 'type', 'title', 'description', 'customer_id', 'customer_service_id', 'ticket_id', 'priority', 'status', 'scheduled_at', 'scheduled_end_at', 'created_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'scheduled_end_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class, 'customer_service_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_order_assignments')->withPivot('role')->withTimestamps();
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(WorkOrderChecklist::class)->orderBy('sort')->orderBy('id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(WorkOrderMedia::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkOrderMaterial::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(WorkOrderStatusHistory::class)->latest('id');
    }

    public function isAssignedTo(?User $user): bool
    {
        return $user && $this->technicians->contains('id', $user->id);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function statusBadge(): string
    {
        return ['open' => 'pending', 'scheduled' => 'pending', 'in_progress' => 'running', 'on_hold' => 'muted', 'completed' => 'done', 'cancelled' => 'void'][$this->status] ?? 'muted';
    }

    /** Scope: hanya WO yang ditugaskan ke user (untuk teknisi). */
    public function scopeAssignedTo($q, int $userId)
    {
        return $q->whereHas('technicians', fn ($t) => $t->where('users.id', $userId));
    }
}
