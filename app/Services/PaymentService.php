<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alur pembayaran: dicatat (pending) → diverifikasi (alokasi ke invoice) → bisa di-reversal.
 * Invoice hanya lunas dari pembayaran TERVERIFIKASI. Transaksi terposting tidak dihapus.
 * @2026-Alifiandra
 */
class PaymentService
{
    public function __construct(protected NotificationService $notifications) {}

    public function record(Tenant $tenant, array $data, ?string $proofPath = null): Payment
    {
        if (! Money::isPositive($data['amount'])) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih dari 0.');
        }

        return DB::transaction(function () use ($tenant, $data, $proofPath) {
            if (! empty($data['idempotency_key'])) {
                $existing = Payment::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            $payment = Payment::create([
                'customer_id' => $data['customer_id'],
                'number' => SequenceService::format($tenant->id, 'PAY', now($tenant->timezone)->format('Ym')),
                'amount' => Money::of($data['amount']),
                'unallocated_amount' => '0',
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'status' => 'pending',
                'proof_path' => $proofPath,
                'paid_at' => $data['paid_at'] ?? now(),
                'received_by' => \Illuminate\Support\Facades\Auth::guard('web')->id(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'planned_invoice_ids' => ! empty($data['invoice_ids']) ? array_values(array_map('intval', $data['invoice_ids'])) : null,
                'notes' => $data['notes'] ?? null,
            ]);

            AuditLogger::log('payment.recorded', $payment, null, $payment->only(['number', 'amount', 'method', 'reference', 'customer_id']));

            return $payment;
        });
    }

    /**
     * Verifikasi & alokasi: ke invoice yang dipilih, sisanya ke invoice terbuka tertua.
     * Kelebihan dicatat sebagai unallocated_amount (deposit/pengecualian).
     */
    public function verify(Tenant $tenant, Payment $payment, ?array $invoiceIds = null, bool $notify = true): Payment
    {
        $payment = DB::transaction(function () use ($payment, $invoiceIds) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'pending') {
                throw new InvalidArgumentException('Hanya pembayaran berstatus pending yang dapat diverifikasi.');
            }

            $invoiceIds ??= $this->plannedInvoices($payment);

            $query = Invoice::where('customer_id', $payment->customer_id)->whereIn('status', ['unpaid', 'partial'])->lockForUpdate();
            $targets = $query->get()->sortBy(function ($inv) use ($invoiceIds) {
                $pos = $invoiceIds ? array_search($inv->id, $invoiceIds) : false;

                return [$pos === false ? 1 : 0, $pos === false ? 0 : $pos, $inv->due_date->timestamp, $inv->id];
            });

            $remaining = (string) Money::of($payment->amount);
            foreach ($targets as $invoice) {
                if (! Money::isPositive($remaining)) {
                    break;
                }
                $alloc = Money::min($remaining, $invoice->balance());
                if (! Money::isPositive($alloc)) {
                    continue;
                }
                $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $alloc]);
                $remaining = Money::sub($remaining, $alloc);
            }

            $payment->forceFill([
                'status' => 'verified',
                'verified_by' => \Illuminate\Support\Facades\Auth::guard('web')->id(),
                'verified_at' => now(),
                'unallocated_amount' => $remaining,
            ])->save();

            foreach ($targets as $invoice) {
                $invoice->recalculate();
            }

            AuditLogger::log('payment.verified', $payment, ['status' => 'pending'], [
                'status' => 'verified',
                'allocations' => $payment->allocations()->get(['invoice_id', 'amount'])->toArray(),
                'unallocated' => $remaining,
            ]);

            return $payment;
        });

        if ($notify) {
            $this->notifications->paymentReceived($tenant, $payment);
        }

        return $payment;
    }

    public function reject(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'pending') {
                throw new InvalidArgumentException('Hanya pembayaran pending yang dapat ditolak.');
            }
            $payment->forceFill(['status' => 'rejected', 'reversal_reason' => $reason])->save();
            AuditLogger::log('payment.rejected', $payment, ['status' => 'pending'], ['status' => 'rejected'], $reason);
        });
    }

    /** Reversal: pembayaran tidak dihapus, statusnya dibalik & invoice dihitung ulang. */
    public function reverse(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'verified') {
                throw new InvalidArgumentException('Hanya pembayaran terverifikasi yang dapat di-reversal.');
            }
            $payment->forceFill([
                'status' => 'reversed', 'reversed_by' => \Illuminate\Support\Facades\Auth::guard('web')->id(), 'reversed_at' => now(), 'reversal_reason' => $reason,
            ])->save();

            $invoices = Invoice::whereIn('id', $payment->allocations()->pluck('invoice_id'))->lockForUpdate()->get();
            foreach ($invoices as $invoice) {
                $invoice->recalculate();
            }

            AuditLogger::log('payment.reversed', $payment, ['status' => 'verified'], ['status' => 'reversed'], $reason);
        });
    }

    protected function plannedInvoices(Payment $payment): array
    {
        return $payment->planned_invoice_ids ?? [];
    }
}
