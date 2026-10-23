<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * Pengingat tagihan H-7..H+7 sesuai pengaturan tenant.
 * Hanya invoice yang belum lunas → otomatis berhenti setelah pembayaran terverifikasi.
 * Dedupe per (invoice, offset) sehingga aman dijalankan berkali-kali.
 */
class SendReminders extends Command
{
    protected $signature = 'billing:send-reminders {--tenant=}';

    protected $description = 'Antre pengingat WhatsApp untuk invoice yang belum lunas';

    public function handle(NotificationService $notifications, TenantContext $context): int
    {
        $tenants = Tenant::whereIn('status', ['trial', 'active'])->when($this->option('tenant'), fn ($q, $c) => $q->where('code', $c))->get();

        foreach ($tenants as $tenant) {
            $offsets = $tenant->setting('reminder_offsets', [-7, -3, -1, 0, 1, 3, 7]);
            $today = now($tenant->timezone)->startOfDay();
            $queued = 0;

            $context->run($tenant, function () use ($tenant, $offsets, $today, $notifications, &$queued) {
                Invoice::with('customer', 'service.package')->whereIn('status', ['unpaid', 'partial'])
                    ->whereBetween('due_date', [$today->copy()->subDays(7)->toDateString(), $today->copy()->addDays(7)->toDateString()])
                    ->chunkById(200, function ($invoices) use ($tenant, $offsets, $today, $notifications, &$queued) {
                        foreach ($invoices as $invoice) {
                            $offset = (int) $today->diffInDays($invoice->due_date->copy()->startOfDay(), false) * -1; // H-n negatif
                            if (! in_array($offset, $offsets, true)) {
                                continue;
                            }
                            $event = $offset < 0 ? 'reminder_before_due' : ($offset === 0 ? 'due_today' : 'overdue');
                            if ($notifications->invoiceEvent($tenant, $invoice, $event, 'H'.$offset)) {
                                $queued++;
                            }
                        }
                    });
            });

            $this->line("{$tenant->code}: {$queued} pengingat diantre");
        }

        return self::SUCCESS;
    }
}
