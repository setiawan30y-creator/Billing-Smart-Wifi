<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NetworkStatusCheck extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['check', 'scope_type', 'scope_id', 'observed', 'threshold', 'triggered', 'network_incident_id', 'checked_at'];

    protected function casts(): array
    {
        return ['triggered' => 'boolean', 'checked_at' => 'datetime'];
    }
}
