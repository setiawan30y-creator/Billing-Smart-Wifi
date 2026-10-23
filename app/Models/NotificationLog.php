<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use BelongsToTenant;

    protected $fillable = ['event', 'channel', 'recipient', 'message', 'dedupe_key', 'status', 'attempts', 'provider_message_id', 'error', 'notifiable_type', 'notifiable_id', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
