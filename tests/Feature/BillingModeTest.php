<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Prabayar vs pascabayar. @2026-Alifiandra */
class BillingModeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant();
    }

    protected function service(string $mode, string $start = '2026-01-01'): CustomerService
    {
        return $this->inTenant($this->tenant, function () use ($mode, $start) {
            $pkg = ServicePackage::firstOrCreate(['code' => 'H'], ['name' => 'Home', 'price' => 150000, 'is_active' => true]);
            $c = new Customer(['name' => 'Pelanggan '.$mode, 'phone' => '0812345'.random_int(10000, 99999), 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
            $c->customer_code = 'PLG-'.uniqid();
            $c->save();

            return $c->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'billing_day' => 5, 'due_days' => 10,
                'billing_mode' => $mode, 'started_at' => $start]);
        });
    }

    public function test_prepaid_bills_current_month_and_postpaid_bills_previous_month(): void
    {
        $pre = $this->service('prepaid');
        $post = $this->service('postpaid');

        $r = app(InvoiceService::class)->generateForPeriod($this->tenant, '2026-10', false);
        $this->assertSame(2, $r['created']);

        $this->inTenant($this->tenant, function () use ($pre, $post) {
            $a = Invoice::with('items')->where('customer_service_id', $pre->id)->sole();
            $b = Invoice::with('items')->where('customer_service_id', $post->id)->sole();
            $this->assertSame('2026-10', $a->period);
            $this->assertStringContainsString('prabayar', $a->items->first()->description);
            $this->assertSame('2026-09', $b->period);
            $this->assertStringContainsString('pemakaian periode 2026-09 (pascabayar)', $b->items->first()->description);
            // Keduanya terbit di bulan penagihan yang sama
            $this->assertSame('2026-10-05', $a->issue_date->toDateString());
            $this->assertSame('2026-10-05', $b->issue_date->toDateString());
        });

        // Idempoten
        $this->assertSame(0, app(InvoiceService::class)->generateForPeriod($this->tenant, '2026-10', false)['created']);
    }

    public function test_postpaid_new_service_is_not_billed_before_any_usage(): void
    {
        $this->service('postpaid', '2026-10-15');
        $svc = app(InvoiceService::class);
        $this->assertSame(0, $svc->generateForPeriod($this->tenant, '2026-10', false)['created']);
        $this->assertSame(1, $svc->generateForPeriod($this->tenant, '2026-11', false)['created']);
        $this->assertSame('2026-10', $this->inTenant($this->tenant, fn () => Invoice::sole()->period));
    }

    public function test_switching_prepaid_to_postpaid_does_not_double_bill(): void
    {
        $s = $this->service('prepaid');
        $svc = app(InvoiceService::class);
        $svc->generateForPeriod($this->tenant, '2026-10', false); // tagih Oktober (di muka)
        $this->inTenant($this->tenant, fn () => $s->update(['billing_mode' => 'postpaid']));
        $svc->generateForPeriod($this->tenant, '2026-11', false); // pascabayar → pemakaian Oktober, sudah ditagih
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => Invoice::count()));
    }

    public function test_billing_mode_option_in_forms_and_settings_default(): void
    {
        $admin = $this->tenantUser($this->tenant);
        $this->actingAs($admin)->put(route('app.settings.update'), [
            'theme' => 'default', 'quota_policy' => 'warn', 'invoice_prefix' => 'INV', 'default_billing_mode' => 'postpaid', 'billing_mode_scope' => 'per_service',
        ])->assertSessionHasNoErrors();
        $this->assertSame('postpaid', $this->tenant->fresh()->setting('default_billing_mode'));

        $c = $this->inTenant($this->tenant, function () {
            ServicePackage::create(['code' => 'X', 'name' => 'Paket X', 'price' => 100000, 'is_active' => true]);
            $c = new Customer(['name' => 'Pelanggan baru', 'phone' => '081200001111', 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
            $c->customer_code = 'PLG-NEW';
            $c->save();

            return $c;
        });
        $pkg = $this->inTenant($this->tenant, fn () => ServicePackage::where('code', 'X')->first());

        $this->actingAs($admin)->get(route('app.customers.show', $c))->assertOk()->assertSee('Mode tagihan')->assertSee('Pascabayar');
        // Tanpa memilih mode → ikut default tenant (pascabayar)
        $this->actingAs($admin)->post(route('app.services.store', $c), [
            'service_package_id' => $pkg->id, 'started_at' => '2026-10-01', 'billing_day' => 1, 'due_days' => 10,
        ])->assertSessionHasNoErrors();
        $svc = $this->inTenant($this->tenant, fn () => CustomerService::where('customer_id', $c->id)->sole());
        $this->assertSame('postpaid', $svc->billing_mode);

        // Ubah ke prabayar → peringatan peralihan
        $this->actingAs($admin)->put(route('app.services.update', $svc), [
            'service_package_id' => $pkg->id, 'status' => 'active', 'billing_day' => 1, 'due_days' => 10, 'billing_mode' => 'prepaid',
        ])->assertSessionHasNoErrors()->assertSessionHas('warnings');
        $this->assertSame('prepaid', $svc->fresh()->billing_mode);
    }

    public function test_uniform_mode_changes_all_services_and_locks_per_service_choice(): void
    {
        $a = $this->service('prepaid');
        $b = $this->service('prepaid');
        $admin = $this->tenantUser($this->tenant);

        $this->assertTrue($this->tenant->billingModeUniform(), 'Default: semua pelanggan ikut mode ISP');
        $this->assertSame('prepaid', $this->tenant->defaultBillingMode());

        $this->actingAs($admin)->put(route('app.settings.update'), [
            'theme' => 'default', 'quota_policy' => 'warn', 'invoice_prefix' => 'INV', 'default_billing_mode' => 'postpaid', 'billing_mode_scope' => 'all',
        ])->assertSessionHasNoErrors()->assertSessionHas('warnings');
        $this->assertSame(['postpaid', 'postpaid'], [$a->fresh()->billing_mode, $b->fresh()->billing_mode]);

        // Pilihan per layanan diabaikan saat mode seragam
        $pkg = $a->service_package_id;
        $this->actingAs($admin)->put(route('app.services.update', $a), [
            'service_package_id' => $pkg, 'status' => 'active', 'billing_day' => 5, 'due_days' => 10, 'billing_mode' => 'prepaid',
        ])->assertSessionHasNoErrors();
        $this->assertSame('postpaid', $a->fresh()->billing_mode);
        $this->actingAs($admin)->post(route('app.services.store', $a->customer_id), [
            'service_package_id' => $pkg, 'started_at' => '2026-10-01', 'billing_day' => 1, 'due_days' => 10, 'billing_mode' => 'prepaid',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->inTenant($this->tenant, fn () => CustomerService::where('billing_mode', 'prepaid')->count()));

        $this->actingAs($admin)->get(route('app.customers.show', $a->customer_id))->assertOk()->assertSee('mengikuti pengaturan ISP');

        // Kembali ke prabayar → semua ikut + peringatan peralihan
        $this->actingAs($admin)->put(route('app.settings.update'), [
            'theme' => 'default', 'quota_policy' => 'warn', 'invoice_prefix' => 'INV', 'default_billing_mode' => 'prepaid', 'billing_mode_scope' => 'all',
        ])->assertSessionHas('warnings', fn ($w) => str_contains(implode(' ', $w), 'pascabayar ke prabayar'));
        $this->assertSame(0, $this->inTenant($this->tenant, fn () => CustomerService::where('billing_mode', 'postpaid')->count()));
    }
}
