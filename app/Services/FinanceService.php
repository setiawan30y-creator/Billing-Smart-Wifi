<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\FinancialAdjustment;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Audit keuangan: penyesuaian (credit note / write-off) dengan persetujuan (maker-checker)
 * dan penutupan periode. Transaksi terposting tidak diubah; koreksi lewat penyesuaian.
 * @2026-Alifiandra
 */
class FinanceService
{
    public function requestAdjustment(Tenant $tenant, Invoice $invoice, string $type, string $amount, string $reason): FinancialAdjustment
    {
        if ($invoice->status === 'void') {
            throw new InvalidArgumentException('Invoice void tidak dapat disesuaikan.');
        }
        if (! Money::isPositive($amount) || Money::cmp($amount, $invoice->balance()) > 0) {
            throw new InvalidArgumentException('Nominal penyesuaian harus > 0 dan tidak melebihi sisa tagihan ('.rupiah($invoice->balance()).').');
        }
        $adj = FinancialAdjustment::create([
            'number' => SequenceService::format($tenant->id, 'ADJ', now($tenant->timezone)->format('Ym')),
            'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'type' => $type,
            'amount' => Money::of($amount), 'reason' => $reason, 'status' => 'pending', 'created_by' => Auth::guard('web')->id(),
        ]);
        AuditLogger::log('adjustment.requested', $adj, null, $adj->only(['number', 'type', 'amount', 'invoice_id']), $reason);

        return $adj;
    }

    public function review(FinancialAdjustment $adj, bool $approve, ?string $note): void
    {
        if ($adj->status !== 'pending') {
            throw new InvalidArgumentException('Penyesuaian ini sudah ditinjau.');
        }
        if ($adj->created_by && $adj->created_by === Auth::guard('web')->id()) {
            throw new InvalidArgumentException('Penyesuaian harus disetujui oleh pengguna lain (maker-checker).');
        }
        DB::transaction(function () use ($adj, $approve, $note) {
            $invoice = $adj->invoice_id ? Invoice::lockForUpdate()->find($adj->invoice_id) : null;
            if ($approve && $invoice && Money::cmp($adj->amount, $invoice->balance()) > 0) {
                throw new InvalidArgumentException('Sisa tagihan sudah berubah dan lebih kecil dari nominal penyesuaian.');
            }
            $adj->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => Auth::guard('web')->id(), 'approved_at' => now(), 'review_note' => $note]);
            $invoice?->recalculate();
            AuditLogger::log($approve ? 'adjustment.approved' : 'adjustment.rejected', $adj, ['status' => 'pending'], ['status' => $adj->status], $note);
        });
    }

    public function closePeriod(Tenant $tenant, string $period, ?string $note): AccountingPeriod
    {
        if ($period >= now($tenant->timezone)->format('Y-m')) {
            throw new InvalidArgumentException('Hanya periode yang sudah lewat yang dapat ditutup.');
        }
        $p = AccountingPeriod::updateOrCreate(['period' => $period], ['status' => 'closed', 'closed_by' => Auth::guard('web')->id(), 'closed_at' => now(), 'note' => $note]);
        AuditLogger::log('period.closed', $p, null, ['period' => $period], $note);

        return $p;
    }

    public function reopenPeriod(AccountingPeriod $p, string $reason): void
    {
        $p->update(['status' => 'open', 'note' => $reason]);
        AuditLogger::log('period.reopened', $p, ['status' => 'closed'], ['status' => 'open'], $reason);
    }

    public static function assertOpen($date, string $what): void
    {
        if ($date && AccountingPeriod::isClosed($date)) {
            throw new InvalidArgumentException("Periode ".\Illuminate\Support\Carbon::parse($date)->format('m/Y')." sudah ditutup — {$what} tidak diizinkan. Gunakan penyesuaian di periode berjalan.");
        }
    }
}
