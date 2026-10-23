<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementDelivery extends Model
{
    protected $fillable = ['service_announcement_id', 'customer_id', 'notification_log_id', 'status'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class, 'notification_log_id');
    }
}
