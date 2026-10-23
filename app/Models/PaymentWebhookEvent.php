<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = ['tenant_id', 'provider', 'event_id', 'signature_valid', 'status', 'payload', 'message', 'payment_id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean'];
    }
}
