<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['open' => 'Baru', 'in_progress' => 'Ditangani', 'waiting_customer' => 'Menunggu pelanggan', 'resolved' => 'Selesai', 'closed' => 'Ditutup'];

    public const CATEGORIES = ['gangguan' => 'Gangguan internet', 'tagihan' => 'Tagihan & pembayaran', 'pemasangan' => 'Pemasangan / pindah alamat', 'lainnya' => 'Lainnya'];

    public const PRIORITIES = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Mendesak'];

    protected $fillable = ['customer_id', 'number', 'subject', 'category', 'priority', 'status', 'source', 'assigned_to', 'created_by'];

    protected function casts(): array
    {
        return ['first_response_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['resolved', 'closed'], true);
    }

    public function statusBadge(): string
    {
        return ['open' => 'pending', 'in_progress' => 'running', 'waiting_customer' => 'muted', 'resolved' => 'done', 'closed' => 'void'][$this->status] ?? 'muted';
    }
}
