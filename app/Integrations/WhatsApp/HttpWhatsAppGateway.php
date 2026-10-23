<?php

namespace App\Integrations\WhatsApp;

use App\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Adapter HTTP generik: POST JSON {number, message} dengan Bearer token.
 * Nama field dapat disesuaikan lewat .env setelah dokumentasi API gateway diverifikasi.
 */
class HttpWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(protected array $config) {}

    public function send(string $number, string $message, array $context = []): array
    {
        if (empty($this->config['url'])) {
            return ['ok' => false, 'message_id' => null, 'error' => 'WHATSAPP_HTTP_URL belum diatur.'];
        }

        try {
            $res = Http::timeout($this->config['timeout'] ?? 15)
                ->withToken((string) ($this->config['token'] ?? ''))
                ->acceptJson()
                ->post($this->config['url'], [
                    $this->config['number_field'] ?? 'number' => $number,
                    $this->config['message_field'] ?? 'message' => $message,
                ]);

            if ($res->successful()) {
                return ['ok' => true, 'message_id' => (string) ($res->json('id') ?? $res->json('data.id') ?? ''), 'error' => null];
            }

            return ['ok' => false, 'message_id' => null, 'error' => 'HTTP '.$res->status()];
        } catch (Throwable $e) {
            return ['ok' => false, 'message_id' => null, 'error' => class_basename($e).': '.mb_substr($e->getMessage(), 0, 200)];
        }
    }
}
