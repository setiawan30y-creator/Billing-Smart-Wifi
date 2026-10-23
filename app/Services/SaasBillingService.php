<?php

namespace App\Services;

use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tagihan sewa platform ALMARA kepada ISP (terpisah dari tagihan internet ISP ke pelanggan).
 * Harga & kuota diambil dari snapshot langganan; paket yang harganya belum disahkan
 * menghasilkan invoice berlabel SIMULASI (bukan tagihan nyata).
 * @2026-Alifiandra
 */
class SaasBillingService
{
    public function generate(Tenant $tenant, string $period): SaasInvoice
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException('Format periode harus YYYY-MM.');
        }

        $sub = $tenant->currentSubscription()->with('plan')->first();
        if (! $sub) {
            throw new InvalidArgumentException('Tenant tidak memiliki langganan aktif/trial.');
        }
        if ($sub->price === null) {
            throw new InvalidArgumentException('Paket custom/kontrak: harga belum ditetapkan pada langganan.');
        }
        if (SaasInvoice::where('tenant_id', $tenant->id)->where('period', $period)->exists()) {
            throw new InvalidArgumentException("Invoice SaaS periode {$period} sudah ada.");
        }

        return DB::transaction(function () use ($tenant, $sub, $period) {
            $usage = UsageMeter::snapshot($tenant, $period);
            $items = [[
                'type' => 'base', 'description' => 'Sewa paket '.$sub->plan->name.' ('.$period.')',
                'quantity' => 1, 'unit_price' => (string) $sub->price, 'amount' => (string) Money::of($sub->price),
            ]];

            if ($sub->client_quota !== null && $usage->active_clients > $sub->client_quota) {
                $extra = $usage->active_clients - $sub->client_quota;
                $items[] = [
                    'type' => 'overage_client',
                    'description' => "Tambahan client aktif: {$usage->active_clients} terpakai, kuota {$sub->client_quota} (snapshot ".$usage->measured_at->format('d/m/Y H:i').')',
                    'quantity' => $extra, 'unit_price' => (string) $sub->overage_client_price,
                    'amount' => Money::mul($sub->overage_client_price, $extra),
                ];
            }
            if ($sub->router_quota !== null && $usage->routers > $sub->router_quota) {
                $extra = $usage->routers - $sub->router_quota;
                $items[] = [
                    'type' => 'overage_router',
                    'description' => "Tambahan MikroTik: {$usage->routers} terdaftar, kuota {$sub->router_quota}",
                    'quantity' => $extra, 'unit_price' => (string) $sub->overage_router_price,
                    'amount' => Money::mul($sub->overage_router_price, $extra),
                ];
            }

            $subtotal = Money::add(...array_column($items, 'amount'));
            $isSimulation = ! $sub->plan->is_price_approved;

            $invoice = SaasInvoice::create([
                'tenant_id' => $tenant->id,
                'tenant_subscription_id' => $sub->id,
                'saas_usage_record_id' => $usage->id,
                'number' => SequenceService::format(null, $isSimulation ? 'SIM' : 'SAAS', str_replace('-', '', $period)),
                'period' => $period,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'subtotal' => $subtotal,
                'tax' => '0',
                'total' => $subtotal,
                'amount_paid' => '0',
                'status' => 'unpaid',
                'is_simulation' => $isSimulation,
                'notes' => $isSimulation ? 'SIMULASI — harga paket masih usulan dan belum disahkan. Bukan tagihan nyata.' : null,
            ]);
            $invoice->items()->createMany($items);

            AuditLogger::log('saas_invoice.generated', $invoice, null, ['number' => $invoice->number, 'total' => $subtotal, 'simulation' => $isSimulation], null, $tenant->id);

            return $invoice;
        });
    }

    public function recordPayment(SaasInvoice $invoice, array $data): SaasPayment
    {
        if ($invoice->is_simulation) {
            throw new InvalidArgumentException('Invoice simulasi tidak dapat menerima pembayaran.');
        }
        if (! Money::isPositive($data['amount'])) {
            throw new InvalidArgumentException('Nominal harus lebih dari 0.');
        }

        return DB::transaction(function () use ($invoice, $data) {
            $invoice = SaasInvoice::lockForUpdate()->findOrFail($invoice->id);
            $payment = $invoice->payments()->create([
                'amount' => Money::of($data['amount']),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'status' => 'verified',
                'recorded_by' => auth()->id(),
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $invoice->recalculatePaid();
            AuditLogger::log('saas_payment.recorded', $payment, null, $payment->only(['amount', 'method', 'reference']), null, $invoice->tenant_id);

            return $payment;
        });
    }

    public function reversePayment(SaasPayment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $payment = SaasPayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'verified') {
                throw new InvalidArgumentException('Pembayaran sudah dibalik.');
            }
            $payment->update(['status' => 'reversed']);
            $payment->invoice->recalculatePaid();
            AuditLogger::log('saas_payment.reversed', $payment, ['status' => 'verified'], ['status' => 'reversed'], $reason, $payment->invoice->tenant_id);
        });
    }
}
