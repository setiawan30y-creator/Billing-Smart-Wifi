<?php

namespace App\Integrations\WhatsApp;

use App\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Driver default: pesan hanya dicatat ke log (tidak benar-benar terkirim). */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $number, string $message, array $context = []): array
    {
        Log::info('[WhatsApp:log] '.$number, ['length' => strlen($message)] + $context);

        return ['ok' => true, 'message_id' => 'log-'.Str::uuid(), 'error' => null];
    }
}
