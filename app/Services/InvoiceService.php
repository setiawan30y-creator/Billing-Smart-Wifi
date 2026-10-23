<?php

namespace App\Services;

use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pembuatan invoice pelanggan. Idempoten per (layanan, periode): dijalankan berulang
 * oleh scheduler atau tombol manual tidak membuat invoice ganda.
 * @2026-Alifiandra
 */
class InvoiceService
{
    public function __construct(protected NotificationService $notifications) {}

    /** @return array{created: int, skipped: int} */
    public function generateForPeriod(Tenant $tenant, string $period, bool $notify = true): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException('Format periode harus YYYY-MM.');
        }

        return app(TenantContext::class)->run($tenant, function () use ($tenant, $period, $notify) {
            $created = 0;
            $skipped = 0;
            $periodStart = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01', $tenant->timezone)->startOfDay();

            CustomerService::with(['package', 'customer'])
                ->where('status', 'active')
                ->whereDate('started_at', '<=', $periodStart->endOfMonth())
                ->whereHas('customer', fn ($q) => $q->where('status', 'active'))
                ->chunkById(200, function ($services) use ($tenant, $period, $periodStart, $notify, &$created, &$skipped) {
                    foreach ($services as $service) {
                        $invoice = $this->createRecurring($tenant, $service, $period, $periodStart);
                        if ($invoice) {
                            $created++;
                            if ($notify) {
                                $this->notifications->invoiceEvent($tenant, $invoice, 'invoice_created');
                            }
                        } else {
                            $skipped++;
                        }
                    }
                });

            AuditLogger::log('invoices.generated', null, null, ['period' => $period, 'created' => $created, 'skipped' => $skipped], null, $tenant->id);

            return compact('created', 'skipped');
        });
    }

    protected function createRecurring(Tenant $tenant, CustomerService $service, string $period, CarbonImmutable $periodStart): ?Invoice
    {
        if (Invoice::where('customer_service_id', $service->id)->where('period', $period)->exists()) {
            return null;
        }

        $day = min(max(1, (int) $service->billing_day), 28);
        $issue = $periodStart->setDay($day);
        $due = $issue->addDays((int) $service->due_days);
        $price = $service->monthlyPrice();

        try {
            return DB::transaction(function () use ($tenant, $service, $period, $issue, $due, $price) {
                $invoice = Invoice::create([
                    'customer_id' => $service->customer_id,
                    'customer_service_id' => $service->id,
                    'number' => $this->nextNumber($tenant, $period),
                    'period' => $period,
                    'issue_date' => $issue,
                    'due_date' => $due,
                    'subtotal' => $price,
                    'discount' => '0',
                    'tax' => '0',
                    'total' => $price,
                    'amount_paid' => '0',
                    'status' => 'unpaid',
                ]);
                $invoice->items()->create([
                    'description' => 'Internet '.$service->package->name.' periode '.$period,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'amount' => $price,
                ]);

                return $invoice;
            });
        } catch (UniqueConstraintViolationException) {
            return null; // dibuat proses lain secara bersamaan
        }
    }

    /** Invoice manual (biaya pasang, perangkat, dll). $items = [[description, quantity, unit_price], ...] */
    public function createManual(Tenant $tenant, int $customerId, array $items, string $issueDate, string $dueDate, ?string $discount = null, ?string $notes = null): Invoice
    {
        return DB::transaction(function () use ($tenant, $customerId, $items, $issueDate, $dueDate, $discount, $notes) {
            $subtotal = '0';
            $rows = [];
            foreach ($items as $it) {
                $amount = Money::mul($it['unit_price'], $it['quantity']);
                $subtotal = Money::add($subtotal, $amount);
                $rows[] = ['description' => $it['description'], 'quantity' => $it['quantity'], 'unit_price' => Money::of($it['unit_price']), 'amount' => $amount];
            }
            $discount = Money::of($discount ?? 0);
            if (Money::cmp($discount, $subtotal) > 0) {
                throw new InvalidArgumentException('Diskon tidak boleh melebihi subtotal.');
            }
            $total = Money::sub($subtotal, $discount);

            $invoice = Invoice::create([
                'customer_id' => $customerId,
                'number' => $this->nextNumber($tenant, substr($issueDate, 0, 7)),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'discount' => (string) $discount,
                'tax' => '0',
                'total' => $total,
                'amount_paid' => '0',
                'status' => Money::isPositive($total) ? 'unpaid' : 'paid',
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
            $invoice->items()->createMany($rows);
            AuditLogger::log('invoice.created', $invoice, null, ['number' => $invoice->number, 'total' => $total]);

            return $invoice;
        });
    }

    /** Invoice yang sudah ada pembayaran terverifikasi tidak bisa di-void; lakukan reversal dahulu. */
    public function void(Invoice $invoice, string $reason): void
    {
        DB::transaction(function () use ($invoice, $reason) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === 'void') {
                return;
            }
            if (Money::isPositive($invoice->amount_paid)) {
                throw new InvalidArgumentException('Invoice sudah memiliki pembayaran terverifikasi. Lakukan reversal pembayaran terlebih dahulu.');
            }
            $before = $invoice->only(['status']);
            $invoice->status = 'void';
            $invoice->void_reason = $reason;
            $invoice->save();
            AuditLogger::log('invoice.voided', $invoice, $before, ['status' => 'void'], $reason);
        });
    }

    protected function nextNumber(Tenant $tenant, string $period): string
    {
        $prefix = $tenant->setting('invoice_prefix', 'INV');

        return SequenceService::format($tenant->id, $prefix, str_replace('-', '', $period));
    }
}
