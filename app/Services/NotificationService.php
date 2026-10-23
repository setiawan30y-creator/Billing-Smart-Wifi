<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Render template WhatsApp tenant & antre pengiriman dengan deduplikasi.
 * @2026-Alifiandra
 */
class NotificationService
{
    public function invoiceEvent(Tenant $tenant, Invoice $invoice, string $event, ?string $dedupeSuffix = null): ?NotificationLog
    {
        $invoice->loadMissing('customer', 'service.package');

        return $this->queue($tenant, $invoice->customer, $event, $this->invoiceData($tenant, $invoice), "{$event}:invoice:{$invoice->id}".($dedupeSuffix ? ':'.$dedupeSuffix : ''), $invoice);
    }

    public function paymentReceived(Tenant $tenant, Payment $payment): void
    {
        $payment->loadMissing('customer', 'allocations.invoice');
        $first = $payment->allocations->first()?->invoice;
        $data = $this->baseData($tenant, $payment->customer) + [
            'jumlah_bayar' => Money::format($payment->amount),
            'nomor_invoice' => $payment->allocations->map(fn ($a) => $a->invoice->number)->implode(', ') ?: '-',
            'periode' => $first?->period ?? '-',
        ];
        $this->queue($tenant, $payment->customer, 'payment_received', $data, 'payment_received:payment:'.$payment->id, $payment);
    }

    public function queue(Tenant $tenant, Customer $customer, string $event, array $data, string $dedupeKey, $notifiable = null): ?NotificationLog
    {
        return app(TenantContext::class)->run($tenant, function () use ($tenant, $customer, $event, $data, $dedupeKey, $notifiable) {
            $template = NotificationTemplate::where('event', $event)->where('channel', 'whatsapp')->first();
            if (! $template && isset(NotificationTemplate::DEFAULTS[$event])) {
                // Template untuk event baru dibuat otomatis dari default (tenant lama).
                $template = NotificationTemplate::create(['event' => $event, 'channel' => 'whatsapp', 'body' => NotificationTemplate::DEFAULTS[$event], 'is_enabled' => true]);
            }
            if (! $template || ! $template->is_enabled || ! $customer->phone) {
                return null;
            }

            if (NotificationLog::where('dedupe_key', $dedupeKey)->exists()) {
                return null;
            }

            try {
                $log = NotificationLog::create([
                    'event' => $event,
                    'channel' => 'whatsapp',
                    'recipient' => self::normalizeNumber($customer->phone),
                    'message' => NotificationTemplate::render($template->body, $data),
                    'dedupe_key' => $dedupeKey,
                    'status' => 'queued',
                    'notifiable_type' => $notifiable?->getMorphClass(),
                    'notifiable_id' => $notifiable?->getKey(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return null;
            }

            SendWhatsAppMessage::dispatch($tenant->id, $log->id)->afterCommit();

            return $log;
        });
    }

    public function invoiceData(Tenant $tenant, Invoice $invoice): array
    {
        return $this->baseData($tenant, $invoice->customer) + [
            'nomor_invoice' => $invoice->number,
            'periode' => $invoice->period ?? $invoice->issue_date->format('Y-m'),
            'total' => Money::format($invoice->total),
            'sisa_tagihan' => Money::format($invoice->balance()),
            'jatuh_tempo' => $invoice->due_date->translatedFormat('d F Y'),
            'paket' => $invoice->service?->package?->name ?? '-',
        ];
    }

    public function baseData(Tenant $tenant, Customer $customer): array
    {
        $tenant->loadMissing('profile');

        return [
            'nama_pelanggan' => $customer->name,
            'id_pelanggan' => $customer->customer_code,
            'nama_isp' => $tenant->displayName(),
            'kontak_isp' => $tenant->profile?->whatsapp ?? '-',
        ];
    }

    public static function sampleData(Tenant $tenant): array
    {
        return [
            'nama_pelanggan' => 'Nama Pelanggan', 'id_pelanggan' => 'PLG-0001', 'nama_isp' => $tenant->displayName(),
            'nomor_invoice' => 'INV/202601/00001', 'periode' => '2026-01', 'total' => 'Rp150.000', 'sisa_tagihan' => 'Rp150.000',
            'jatuh_tempo' => '10 Januari 2026', 'jumlah_bayar' => 'Rp150.000', 'paket' => 'Paket 20 Mbps',
            'kontak_isp' => $tenant->profile?->whatsapp ?? '08xxxxxxxxxx',
        ];
    }

    public static function normalizeNumber(string $phone): string
    {
        $n = preg_replace('/\D/', '', $phone);
        if (str_starts_with($n, '0')) {
            $n = '62'.substr($n, 1);
        }

        return $n;
    }
}
