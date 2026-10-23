<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    use BelongsToTenant;

    protected $table = 'accounting_periods';

    protected $fillable = ['period', 'status', 'closed_by', 'closed_at', 'note'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function closer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Apakah tanggal ini berada di periode yang sudah ditutup? */
    public static function isClosed($date): bool
    {
        $period = \Illuminate\Support\Carbon::parse($date)->format('Y-m');

        return static::where('period', $period)->where('status', 'closed')->exists();
    }
}
