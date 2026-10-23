<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'work_order_status_history';

    protected $fillable = ['work_order_id', 'from_status', 'to_status', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
