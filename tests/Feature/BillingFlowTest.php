<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NetworkDevice;
use App\Models\Payment;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\SaasBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant();
    }

    protected function customerWithService(string $price = '150000', string $name = 'Pelanggan Satu'): Customer
    {
        return $this->inTenant($this->tenant, function () use ($price, $name) {
            $pkg = ServicePackage::firstOrCreate(['code' => 'P'.$price], ['name' => 'Paket '.$price, 'price' => $price, 'is_active' => true]);
            $c = new Customer(['name' => $name, 'phone' => '081234500000', 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
            $c->customer_code = 'PLG-'.uniqid();
            $c->save();
            $c->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'billing_day' => 1, 'due_days' => 10, 'started_at' => '2026-01-01']);

            return $c;
        });
    }

    public function test_invoice_generation_is_idempotent(): void
    {
        $this->customerWithService();
        $svc = app(InvoiceService::class);

        $r1 = $svc->generateForPeriod($this->tenant, '2026-10');
        $r2 = $svc->generateForPeriod($this->tenant, '2026-10');

        $this->assertSame(1, $r1['created']);
        $this->assertSame(0, $r2['created']);
        $this->assertSame(1, $r2['skipped']);
        $inv = $this->inTenant($this->tenant, fn () => Invoice::first());
        $this->assertSame('150000.00', (string) $inv->total);
        $this->assertSame('2026-10-11', $inv->due_date->toDateString());
    }

    public function test_payment_verify_partial_full_and_reversal(): void
    {
        $c = $this->customerWithService();
        app(InvoiceService::class)->generateForPeriod($this->tenant, '2026-10');
        $user = $this->tenantUser($this->tenant, 'billing');
        $invoice = $this->inTenant($this->tenant, fn () => Invoice::first());

        // Partial (pending dulu, invoice belum berubah)
        $this->actingAs($user)->post(route('app.payments.store'), [
            'customer_id' => $c->id, 'amount' => '100.000', 'method' => 'cash', 'paid_at' => now()->subMinute()->format('Y-m-d H:i'),
        ])->assertRedirect();
        $p1 = $this->inTenant($this->tenant, fn () => Payment::first());
        $this->assertSame('pending', $p1->status);
        $this->assertSame('unpaid', $invoice->fresh()->status);

        $this->actingAs($user)->post(route('app.payments.verify', $p1))->assertRedirect();
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame('100000.00', (string) $invoice->fresh()->amount_paid);

        // Overpay: sisa 50.000, bayar 70.000 → 20.000 unallocated
        $this->actingAs($user)->post(route('app.payments.store'), [
            'customer_id' => $c->id, 'amount' => '70000', 'method' => 'transfer', 'reference' => 'TRF-1', 'paid_at' => now()->subMinute()->format('Y-m-d H:i'), 'verify_now' => 1,
        ])->assertRedirect();
        $p2 = $this->inTenant($this->tenant, fn () => Payment::latest('id')->first());
        $this->assertSame('verified', $p2->status);
        $this->assertSame('20000.00', (string) $p2->unallocated_amount);
        $this->assertSame('paid', $invoice->fresh()->status);

        // Reversal p2 → kembali partial
        $this->actingAs($user)->post(route('app.payments.reverse', $p2), ['reason' => 'Dana tidak masuk'])->assertRedirect();
        $this->assertSame('reversed', $p2->fresh()->status);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.reversed', 'reason' => 'Dana tidak masuk']);

        // Void ditolak karena ada pembayaran terverifikasi
        $this->actingAs($user)->post(route('app.invoices.void', $invoice), ['reason' => 'x'])->assertSessionHas('error');
        $this->assertNotSame('void', $invoice->fresh()->status);
    }

    public function test_payment_idempotency_key_prevents_double_submit(): void
    {
        $c = $this->customerWithService();
        $user = $this->tenantUser($this->tenant, 'billing');
        $data = ['customer_id' => $c->id, 'amount' => '50000', 'method' => 'cash', 'paid_at' => now()->subMinute()->format('Y-m-d H:i'), 'idempotency_key' => '7f1b7a4e-3b1d-4b8c-9d0e-1a2b3c4d5e6f'];
        $this->actingAs($user)->post(route('app.payments.store'), $data);
        $this->actingAs($user)->post(route('app.payments.store'), $data);
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => Payment::count()));
    }

    public function test_collector_cannot_verify_payment(): void
    {
        $c = $this->customerWithService();
        $collector = $this->tenantUser($this->tenant, 'collector');
        $this->actingAs($collector)->post(route('app.payments.store'), [
            'customer_id' => $c->id, 'amount' => '50000', 'method' => 'cash', 'paid_at' => now()->subMinute()->format('Y-m-d H:i'), 'verify_now' => 1,
        ])->assertRedirect();
        $p = $this->inTenant($this->tenant, fn () => Payment::first());
        $this->assertSame('pending', $p->status);
        $this->actingAs($collector)->post(route('app.payments.verify', $p))->assertForbidden();
        $this->actingAs($collector)->get(route('app.packages.index'))->assertForbidden();
    }

    public function test_webhook_signature_and_idempotency(): void
    {
        config(['almara.payment_gateway.webhook_secret' => 'rahasia-uji']);
        $this->customerWithService();
        app(InvoiceService::class)->generateForPeriod($this->tenant, '2026-10');
        $invoice = $this->inTenant($this->tenant, fn () => Invoice::first());

        $payload = json_encode(['event_id' => 'evt-1', 'tenant_code' => $this->tenant->code, 'invoice_number' => $invoice->number, 'amount' => '150000', 'currency' => 'IDR', 'status' => 'paid']);

        $this->call('POST', '/webhooks/payment/generic', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'salah'], $payload)->assertStatus(401);
        $this->assertSame('unpaid', $invoice->fresh()->status);

        $sig = hash_hmac('sha256', $payload, 'rahasia-uji');
        $this->call('POST', '/webhooks/payment/generic', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $sig], $payload)->assertOk();
        $this->call('POST', '/webhooks/payment/generic', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $sig], $payload)->assertOk();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => Payment::count()));

        // Nominal tidak cocok → pending untuk rekonsiliasi manual
        $c2 = $this->customerWithService('200000', 'Pelanggan Dua');
        app(InvoiceService::class)->generateForPeriod($this->tenant, '2026-10');
        $inv2 = $this->inTenant($this->tenant, fn () => Invoice::where('customer_id', $c2->id)->first());
        $p2 = json_encode(['event_id' => 'evt-2', 'tenant_code' => $this->tenant->code, 'invoice_number' => $inv2->number, 'amount' => '1000', 'currency' => 'IDR', 'status' => 'paid']);
        $this->call('POST', '/webhooks/payment/generic', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $p2, 'rahasia-uji')], $p2)->assertOk();
        $this->assertSame('unpaid', $inv2->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', ['event_id' => 'evt-2', 'status' => 'unmatched']);
    }

    public function test_saas_invoice_overage_and_simulation_flag(): void
    {
        // Starter: kuota 100 client, 3 router. Buat 102 client & 4 router.
        $this->inTenant($this->tenant, function () {
            $pkg = ServicePackage::create(['code' => 'X', 'name' => 'X', 'price' => 100000, 'is_active' => true]);
            for ($i = 0; $i < 102; $i++) {
                $c = new Customer(['name' => 'C'.$i, 'phone' => '0812', 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
                $c->customer_code = 'C'.$i;
                $c->save();
                $c->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'started_at' => '2026-01-01']);
            }
            // Layanan kedua untuk pelanggan yang sama tidak dihitung ganda.
            Customer::first()->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'started_at' => '2026-01-01']);
            for ($i = 0; $i < 4; $i++) {
                NetworkDevice::create(['name' => 'R'.$i, 'type' => 'mikrotik', 'host' => '10.0.0.'.$i, 'is_active' => true]);
            }
        });

        $inv = app(SaasBillingService::class)->generate($this->tenant, '2026-10');
        // 99.000 + 2 × 300 + 1 × 15.000 = 114.600
        $this->assertSame('114600.00', (string) $inv->total);
        $this->assertTrue($inv->is_simulation);
        $this->assertStringStartsWith('SIM/', $inv->number);
        $this->assertSame(102, $inv->usage->active_clients);
        $this->assertSame(103, $inv->usage->active_services);

        $this->expectException(\InvalidArgumentException::class);
        app(SaasBillingService::class)->recordPayment($inv, ['amount' => '1000', 'method' => 'transfer']);
    }

    public function test_isp_pages_render(): void
    {
        $c = $this->customerWithService();
        app(InvoiceService::class)->generateForPeriod($this->tenant, '2026-10');
        $u = $this->tenantUser($this->tenant);
        $inv = $this->inTenant($this->tenant, fn () => Invoice::first());
        $tpl = $this->inTenant($this->tenant, fn () => \App\Models\NotificationTemplate::first());

        foreach ([route('app.dashboard'), route('app.customers.index'), route('app.customers.create'), route('app.customers.show', $c), route('app.customers.edit', $c),
            route('app.packages.index'), route('app.packages.create'), route('app.invoices.index'), route('app.invoices.create'), route('app.invoices.show', $inv),
            route('app.invoices.print', $inv), route('app.payments.index'), route('app.payments.create'), route('app.payments.create', ['customer' => $c->id]),
            route('app.devices.index'), route('app.devices.create'), route('app.templates.index'), route('app.templates.edit', $tpl), route('app.templates.logs'),
            route('app.users.index'), route('app.users.create'), route('app.settings.edit'), route('app.audit.index')] as $url) {
            $this->actingAs($u)->get($url)->assertOk();
        }
    }
}
