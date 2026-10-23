<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdpPort extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['available' => 'Kosong', 'used' => 'Terpakai', 'broken' => 'Rusak'];

    protected $fillable = ['port_number', 'customer_service_id', 'status', 'notes'];

    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class, 'customer_service_id');
    }

    public function label(): string
    {
        return $this->odp->code.' · port '.$this->port_number;
    }
}
