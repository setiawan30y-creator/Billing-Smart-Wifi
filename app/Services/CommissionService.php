<?php

namespace App\Services;

use App\Models\CollectionAssignment;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Komisi kolektor/agen: hanya dari pembayaran TERVERIFIKASI, satu komisi per pembayaran,
 * reversal pembayaran membalik komisi (void bila belum dibayar, entri negatif bila sudah diselesaikan).
 * @2026-Alifiandra
 */
class CommissionService
{
    public function onPaymentVerified(Payment $payment): ?CommissionEntry
    {
        $collector = $this->collectorFor($payment);
        if (! $collector) {
            return null;
        }
        $rule = $this->ruleFor($collector, $payment);
        if (! $rule) {
            return null;
        }
        $base = Money::sub($payment->amount, $payment->unallocated_amount); // kelebihan bayar tidak dikomisikan
        $amount = $rule->type === 'percent' ? Money::mul($base, (string) ((float) $rule->value / 100)) : (string) Money::of($rule->value);
        if (! Money::isPositive($amount)) {
            return null;
        }

        try {
            return CommissionEntry::create([
                'payment_id' => $payment->id, 'user_id' => $collector->id, 'commission_rule_id' => $rule->id,
                'kind' => 'earn', 'base_amount' => $base, 'amount' => $amount, 'status' => 'pending',
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return null; // sudah ada
        }
    }

    public function onPaymentReversed(Payment $payment): void
    {
        $earn = CommissionEntry::where('payment_id', $payment->id)->where('kind', 'earn')->first();
        if (! $earn || $earn->status === 'void') {
            return;
        }
        if ($earn->status === 'pending') {
            $earn->update(['status' => 'void']);
        } else {
            CommissionEntry::firstOrCreate(['payment_id' => $payment->id, 'kind' => 'reversal'], [
                'user_id' => $earn->user_id, 'commission_rule_id' => $earn->commission_rule_id,
                'base_amount' => $earn->base_amount, 'amount' => Money::sub('0', $earn->amount), 'status' => 'pending',
            ]);
        }
        AuditLogger::log('commission.reversed', $earn, null, ['payment' => $payment->number]);
    }

    public function settle(Tenant $tenant, User $collector, array $entryIds, ?string $reference, ?string $notes): CommissionSettlement
    {
        return DB::transaction(function () use ($tenant, $collector, $entryIds, $reference, $notes) {
            $entries = CommissionEntry::where('user_id', $collector->id)->where('status', 'pending')->whereIn('id', $entryIds)->lockForUpdate()->get();
            if ($entries->isEmpty()) {
                throw new InvalidArgumentException('Tidak ada komisi pending yang dipilih.');
            }
            $total = Money::add(...$entries->pluck('amount')->all());
            if (Money::cmp($total, '0') < 0) {
                throw new InvalidArgumentException('Total komisi negatif — tunggu hingga ada komisi baru untuk menutup koreksi.');
            }
            $s = CommissionSettlement::create([
                'number' => SequenceService::format($tenant->id, 'KOM', now($tenant->timezone)->format('Ym')),
                'user_id' => $collector->id, 'total' => $total, 'entries_count' => $entries->count(),
                'reference' => $reference, 'notes' => $notes, 'paid_at' => now(), 'created_by' => Auth::guard('web')->id(),
            ]);
            CommissionEntry::whereIn('id', $entries->pluck('id'))->update(['status' => 'settled', 'commission_settlement_id' => $s->id]);
            AuditLogger::log('commission.settled', $s, null, ['collector' => $collector->id, 'total' => $total, 'entries' => $entries->count()]);

            return $s;
        });
    }

    protected function collectorFor(Payment $payment): ?User
    {
        $receiver = $payment->received_by ? User::find($payment->received_by) : null;
        if ($receiver && $receiver->hasRole('collector')) {
            return $receiver;
        }
        $assigned = CollectionAssignment::where('customer_id', $payment->customer_id)->first();

        return $assigned ? User::find($assigned->user_id) : null;
    }

    protected function ruleFor(User $collector, Payment $payment): ?CommissionRule
    {
        $base = CommissionRule::where('is_active', true)->whereDate('effective_from', '<=', $payment->paid_at ?? now())
            ->whereIn('method', ['any', $payment->method]);

        return (clone $base)->where('user_id', $collector->id)->latest('effective_from')->first()
            ?? (clone $base)->whereNull('user_id')->latest('effective_from')->first();
    }
}
