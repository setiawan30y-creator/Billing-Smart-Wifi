<?php

namespace App\Contracts;

/**
 * Adapter WhatsApp Gateway. Gateway sudah tersedia sebagai sistem terpisah;
 * aplikasi ini hanya mengirim melalui adapter (tidak membangun ulang gateway).
 */
interface WhatsAppGateway
{
    /** @return array{ok: bool, message_id: ?string, error: ?string} */
    public function send(string $number, string $message, array $context = []): array;
}
