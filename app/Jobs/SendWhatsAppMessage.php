<?php

namespace App\Jobs;

use App\Contracts\WhatsAppGateway;
use App\Models\NotificationLog;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\RateLimiter;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $tenantId, public int $logId) {}

    public function handle(WhatsAppGateway $gateway): void
    {
        app(TenantContext::class)->run($this->tenantId, function () use ($gateway) {
            $log = NotificationLog::find($this->logId);
            if (! $log || in_array($log->status, ['sent', 'delivered', 'read'], true)) {
                return; // sudah terkirim (deduplikasi)
            }

            $key = 'wa-rate:'.$this->tenantId;
            if (RateLimiter::tooManyAttempts($key, config('almara.whatsapp.rate_per_minute', 20))) {
                $this->release(RateLimiter::availableIn($key) + 1);

                return;
            }
            RateLimiter::hit($key, 60);

            $log->increment('attempts');
            $result = $gateway->send($log->recipient, $log->message, ['tenant' => $this->tenantId, 'event' => $log->event]);

            if ($result['ok']) {
                $log->update(['status' => 'sent', 'provider_message_id' => $result['message_id'], 'sent_at' => now(), 'error' => null]);
            } else {
                $log->update(['status' => 'failed', 'error' => $result['error']]);
                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff[$this->attempts() - 1] ?? 900);
                }
            }
        });
    }
}
