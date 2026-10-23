<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianAvailability extends Model
{
    use BelongsToTenant;

    protected $table = 'technician_availability';

    public const STATUSES = ['off' => 'Libur', 'leave' => 'Cuti', 'sick' => 'Sakit'];

    protected $fillable = ['user_id', 'date', 'status', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
