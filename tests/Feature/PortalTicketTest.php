<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PortalTicketTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant('ISP-P');
        $this->customer = $this->inTenant($this->tenant, function () {
            $pkg = ServicePackage::create(['code' => 'H10', 'name' => 'Home 10', 'price' => 150000, 'is_active' => true]);
            $c = new Customer(['name' => 'Pelanggan Portal', 'phone' => '081234567890', 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
            $c->customer_code = 'PLG-00001';
            $c->save();
            $c->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'started_at' => '2026-01-01']);

            return $c;
        });
        app(InvoiceService::class)->generateForPeriod($this->tenant, '2026-10', false);
    }

    protected function activatePortal(): CustomerAccount
    {
        $admin = $this->tenantUser($this->tenant);
        $this->actingAs($admin)->post(route('app.customers.portal', $this->customer))->assertSessionHas('activation_link');
        $link = session('activation_link');
        auth()->guard('web')->logout();

        $path = parse_url($link, PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('Pelanggan Portal');
        $this->post($path, ['password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123'])->assertRedirect(route('portal.home', 'ISP-P'));

        return CustomerAccount::where('customer_id', $this->customer->id)->first();
    }

    public function test_activation_login_and_pages(): void
    {
        $account = $this->activatePortal();
        $this->assertAuthenticatedAs($account, 'customer');

        foreach (['portal.home', 'portal.invoices', 'portal.payments', 'portal.tickets.index', 'portal.tickets.create', 'portal.profile'] as $r) {
            $this->get(route($r, 'ISP-P'))->assertOk();
        }
        $this->get(route('portal.home', 'ISP-P'))->assertSee('Rp150.000');

        $this->post(route('portal.logout', 'ISP-P'));
        $this->assertGuest('customer');
        $this->post('/portal/ISP-P/login', ['customer_code' => 'plg-00001', 'password' => 'Rahasia123'])->assertRedirect(route('portal.home', 'ISP-P'));
        $this->assertAuthenticated('customer');
    }

    public function test_portal_is_isolated_per_tenant_and_customer(): void
    {
        $this->activatePortal();
        $other = $this->makeTenant('ISP-Q');
        $otherInvoice = $this->inTenant($other, function () {
            $c = new Customer(['name' => 'Lain', 'phone' => '0812', 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
            $c->customer_code = 'PLG-00001';
            $c->save();

            return Invoice::create(['customer_id' => $c->id, 'number' => 'X-1', 'issue_date' => now(), 'due_date' => now(), 'subtotal' => 1, 'total' => 1, 'status' => 'unpaid']);
        });

        // Akun ISP-P tidak bisa membuka portal ISP-Q (dipaksa logout).
        $this->get(route('portal.home', 'ISP-Q'))->assertRedirect(route('portal.login', 'ISP-Q'));

        $this->post('/portal/ISP-P/login', ['customer_code' => 'PLG-00001', 'password' => 'Rahasia123']);
        $this->get(route('portal.invoices.show', ['ISP-P', $otherInvoice->id]))->assertNotFound();
    }

    public function test_payment_proof_is_pending_until_staff_verifies(): void
    {
        $this->activatePortal();
        $invoice = $this->inTenant($this->tenant, fn () => Invoice::first());

        $this->post(route('portal.invoices.proof', ['ISP-P', $invoice->id]), [
            'amount' => '150.000', 'paid_at' => now()->subMinute()->format('Y-m-d H:i'), 'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect(route('portal.payments', 'ISP-P'));

        $payment = $this->inTenant($this->tenant, fn () => Payment::first());
        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->received_by);
        $this->assertSame('unpaid', $invoice->fresh()->status);

        // Kirim ulang untuk invoice yang sama ditolak
        $this->post(route('portal.invoices.proof', ['ISP-P', $invoice->id]), [
            'amount' => '150000', 'paid_at' => now()->subMinute()->format('Y-m-d H:i'), 'proof' => UploadedFile::fake()->image('b2.jpg'),
        ])->assertSessionHas('error');

        auth()->guard('customer')->logout();
        $this->actingAs($this->tenantUser($this->tenant, 'billing'), 'web')->post(route('app.payments.verify', $payment))->assertSessionHas('success');
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_ticket_flow_between_portal_and_staff(): void
    {
        $this->activatePortal();
        $this->post(route('portal.tickets.store', 'ISP-P'), ['category' => 'gangguan', 'subject' => 'Internet mati', 'message' => 'Sejak pagi lampu LOS merah'])->assertRedirect();
        $ticket = $this->inTenant($this->tenant, fn () => Ticket::first());
        $this->assertSame('portal', $ticket->source);

        auth()->guard('customer')->logout();
        $noc = $this->tenantUser($this->tenant, 'noc');
        $this->actingAs($noc, 'web')->get(route('app.tickets.index'))->assertOk()->assertSee('Internet mati');
        $this->actingAs($noc, 'web')->post(route('app.tickets.reply', $ticket), ['message' => 'Catatan teknisi: cek ODP', 'internal' => 1]);
        $this->actingAs($noc, 'web')->post(route('app.tickets.reply', $ticket), ['message' => 'Teknisi kami menuju lokasi']);
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->first_response_at);
        $this->actingAs($noc, 'web')->get(route('app.tickets.show', $ticket))->assertOk();

        // Pelanggan tidak melihat catatan internal
        auth()->guard('web')->logout();
        $this->post('/portal/ISP-P/login', ['customer_code' => 'PLG-00001', 'password' => 'Rahasia123']);
        $this->get(route('portal.tickets.show', ['ISP-P', $ticket->id]))->assertOk()->assertSee('Teknisi kami menuju lokasi')->assertDontSee('Catatan teknisi');

        // Billing role tidak boleh membalas tiket? (billing punya tickets.manage) — collector tidak
        auth()->guard('customer')->logout();
        $this->actingAs($this->tenantUser($this->tenant, 'collector'), 'web')->get(route('app.tickets.index'))->assertForbidden();
    }
}
