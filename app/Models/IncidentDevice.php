<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentDevice extends Model
{
    protected $fillable = ['network_incident_id', 'affected_type', 'affected_id'];

    public function incident(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NetworkIncident::class, 'network_incident_id');
    }

    public function label(): string
    {
        return match ($this->affected_type) {
            'odp' => 'ODP '.(Odp::withTrashed()->find($this->affected_id)?->code ?? '#'.$this->affected_id),
            'odc' => 'ODC '.(Odc::withTrashed()->find($this->affected_id)?->code ?? '#'.$this->affected_id),
            default => 'Perangkat '.(NetworkDevice::withTrashed()->find($this->affected_id)?->name ?? '#'.$this->affected_id),
        };
    }
}
