<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentWebhookEvent;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Throwable;

/**
 * Pemrosesan callback Payment Gateway (adapter generik HMAC-SHA256).
 * - Signature wajib valid; event idempoten (provider + event_id unik).
 * - Invoice hanya otomatis lunas bila nominal, mata uang, referensi, & status cocok.
 * - Selain itu masuk alur pengecualian (unmatched) untuk rekonsiliasi manual.
 * Format payload perlu disesuaikan setelah dokumentasi gateway yang dipakai diverifikasi.
 * @2026-Alifiandra
 */
class PaymentWebhookService
{
    public function __construct(protected PaymentService $payments) {}

    public function verifySignature(Request $request): bool
    {
        $secret = config('almara.payment_gateway.webhook_secret');
        $sig = (string) $request->header(config('almara.payment_gateway.signature_header', 'X-Signature'));
        if (! $secret || $sig === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $sig);
    }

    /** @return array{0: int, 1: string} [http status, message] */
    public function handle(Request $request, string $provider): array
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload) || empty($payload['event_id'])) {
            return [422, 'Payload tidak valid.'];
        }

        if (! $this->verifySignature($request)) {
            // Event bersignature salah tidak diproses & tidak mengubah apa pun.
            return [401, 'Signature tidak valid.'];
        }

        try {
            $event = PaymentWebhookEvent::create([
                'provider' => $provider,
                'event_id' => (string) $payload['event_id'],
                'signature_valid' => true,
                'status' => 'received',
                'payload' => $payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            return [200, 'Event sudah diproses (duplikat).'];
        }

        try {
            return $this->process($event, $payload);
        } catch (Throwable $e) {
            $event->update(['status' => 'unmatched', 'message' => mb_substr('Error: '.$e->getMessage(), 0, 250)]);

            return [200, 'Dicatat untuk rekonsiliasi manual.'];
        }
    }

    protected function process(PaymentWebhookEvent $event, array $p): array
    {
        $tenant = Tenant::where('code', $p['tenant_code'] ?? '')->first();
        if (! $tenant) {
            $event->update(['status' => 'unmatched', 'message' => 'Tenant tidak ditemukan.']);

            return [200, 'Unmatched.'];
        }
        $event->update(['tenant_id' => $tenant->id]);

        if (($p['status'] ?? '') !== 'paid') {
            $event->update(['status' => 'processed', 'message' => 'Status '.($p['status'] ?? '-').' dicatat tanpa perubahan invoice.']);

            return [200, 'OK'];
        }

        return app(TenantContext::class)->run($tenant, function () use ($tenant, $event, $p) {
            $invoice = Invoice::where('number', $p['invoice_number'] ?? '')->first();
            if (! $invoice || strtoupper($p['currency'] ?? 'IDR') !== 'IDR') {
                $event->update(['status' => 'unmatched', 'message' => 'Invoice/mata uang tidak cocok.']);

                return [200, 'Unmatched.'];
            }

            $payment = $this->payments->record($tenant, [
                'customer_id' => $invoice->customer_id,
                'amount' => Money::of($p['amount'] ?? 0),
                'method' => 'gateway',
                'reference' => $p['reference'] ?? $event->event_id,
                'idempotency_key' => $event->provider.':'.$event->event_id,
                'invoice_ids' => [$invoice->id],
                'notes' => 'Otomatis dari webhook '.$event->provider,
            ]);
            $event->update(['payment_id' => $payment->id]);

            if ($invoice->status === 'void' || Money::cmp($payment->amount, $invoice->balance()) !== 0) {
                $event->update(['status' => 'unmatched', 'message' => 'Nominal tidak sama dengan sisa tagihan (partial/overpaid) atau invoice void. Perlu verifikasi manual.']);

                return [200, 'Pending manual.'];
            }

            $this->payments->verify($tenant, $payment, [$invoice->id]);
            $event->update(['status' => 'processed', 'message' => 'Invoice '.$invoice->number.' dilunasi.']);

            return [200, 'OK'];
        });
    }
}
