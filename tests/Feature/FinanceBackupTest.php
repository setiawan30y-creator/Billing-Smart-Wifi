<?php

namespace Tests\Feature;

use App\Models\BackupArtifact;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\FinancialAdjustment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReconciliationBatch;
use App\Models\RestoreJob;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FinanceBackupTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant();
        $this->customer = $this->inTenant($this->tenant, function () {
            $pkg = ServicePackage::create(['code' => 'H', 'name' => 'Home', 'price' => 200000, 'is_active' => true]);
            $c = new Customer(['name' => 'Pelanggan Keuangan', 'phone' => '081234567890', 'address' => 'x', 'ownership' => 'own', 'status' => 'active']);
            $c->customer_code = 'PLG-00009';
            $c->save();
            $c->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'started_at' => '2026-01-01']);

            return $c;
        });
        app(InvoiceService::class)->generateForPeriod($this->tenant, now()->format('Y-m'), false);
    }

    public function test_commission_from_verified_payment_reversal_and_settlement(): void
    {
        $collector = $this->tenantUser($this->tenant, 'collector');
        $billing = $this->tenantUser($this->tenant, 'billing');
        $admin = $this->tenantUser($this->tenant);
        $this->actingAs($admin)->post(route('app.commissions.rules.store'), ['name' => '5%', 'type' => 'percent', 'value' => 5, 'method' => 'any', 'effective_from' => now()->subYear()->toDateString()])->assertSessionHas('success');

        $this->actingAs($collector)->post(route('app.payments.store'), ['customer_id' => $this->customer->id, 'amount' => '200000', 'method' => 'cash', 'paid_at' => now()->subMinute()->format('Y-m-d H:i')]);
        $p = $this->inTenant($this->tenant, fn () => Payment::first());
        $this->assertSame(0, $this->inTenant($this->tenant, fn () => CommissionEntry::count()), 'Belum ada komisi sebelum verifikasi');

        $this->actingAs($billing)->post(route('app.payments.verify', $p));
        $e = $this->inTenant($this->tenant, fn () => CommissionEntry::first());
        $this->assertSame('10000.00', (string) $e->amount);
        $this->assertSame($collector->id, $e->user_id);

        $this->actingAs($admin)->post(route('app.commissions.settle'), ['user_id' => $collector->id, 'entries' => [$e->id]])->assertSessionHas('success');
        $this->assertSame('settled', $e->fresh()->status);

        $this->actingAs($billing)->post(route('app.payments.reverse', $p), ['reason' => 'Uang palsu']);
        $rev = $this->inTenant($this->tenant, fn () => CommissionEntry::where('kind', 'reversal')->first());
        $this->assertSame('-10000.00', (string) $rev->amount);

        $this->actingAs($collector)->get(route('app.commissions.index'))->assertOk();
        $this->actingAs($admin)->get(route('app.commissions.assignments'))->assertOk();
    }

    public function test_reconciliation_auto_match_and_verify(): void
    {
        $billing = $this->tenantUser($this->tenant, 'billing');
        $this->actingAs($billing)->post(route('app.payments.store'), ['customer_id' => $this->customer->id, 'amount' => '200000', 'method' => 'transfer', 'reference' => 'FT001', 'paid_at' => now()->subMinute()->format('Y-m-d H:i')]);
        $csv = "tanggal;keterangan;jumlah;referensi\n".now()->format('d/m/Y').";TRSF PLG-00009;200.000;FT001\n".now()->format('d/m/Y').";SETOR TUNAI;55000;\n".now()->format('d/m/Y').";BIAYA ADM;-6500;\n";
        $this->actingAs($billing)->post(route('app.reconciliation.store'), ['file' => UploadedFile::fake()->createWithContent('mutasi.csv', $csv)])->assertRedirect();
        $batch = $this->inTenant($this->tenant, fn () => ReconciliationBatch::first());
        $this->assertSame(2, $batch->total_items, 'Dana keluar diabaikan');
        $this->assertSame(1, $batch->items()->where('status', 'matched')->count());

        $this->actingAs($billing)->post(route('app.reconciliation.verify', $batch))->assertSessionHas('success');
        $this->assertSame('paid', $this->inTenant($this->tenant, fn () => Invoice::first())->status);

        $this->actingAs($billing)->post(route('app.reconciliation.complete', $batch))->assertSessionHas('error');
        $item = $batch->items()->where('status', 'unmatched')->first();
        $this->actingAs($billing)->post(route('app.reconciliation.resolve', [$batch, $item]), ['action' => 'ignore', 'note' => 'Setoran pribadi'])->assertSessionHas('success');
        $this->actingAs($billing)->post(route('app.reconciliation.complete', $batch))->assertSessionHas('success');
        $this->actingAs($billing)->get(route('app.reconciliation.show', $batch))->assertOk();
    }

    public function test_adjustment_maker_checker_and_closed_period(): void
    {
        $billing = $this->tenantUser($this->tenant, 'billing');
        $admin = $this->tenantUser($this->tenant);
        $inv = $this->inTenant($this->tenant, fn () => Invoice::first());

        $this->actingAs($billing)->post(route('app.finance.adjustments.store'), ['invoice_id' => $inv->id, 'type' => 'credit_note', 'amount' => '50000', 'reason' => 'Kompensasi gangguan'])->assertSessionHas('success');
        $adj = $this->inTenant($this->tenant, fn () => FinancialAdjustment::first());
        $this->actingAs($billing)->post(route('app.finance.adjustments.review', $adj), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($admin)->post(route('app.finance.adjustments.review', $adj), ['decision' => 'approve'])->assertSessionHas('success');
        $this->assertSame('150000.00', $inv->fresh()->balance());
        $this->assertSame('partial', $inv->fresh()->status);

        // Maker tidak boleh menyetujui sendiri
        $this->actingAs($admin)->post(route('app.finance.adjustments.store'), ['invoice_id' => $inv->id, 'type' => 'write_off', 'amount' => '10000', 'reason' => 'x']);
        $adj2 = $this->inTenant($this->tenant, fn () => FinancialAdjustment::latest('id')->first());
        $this->actingAs($admin)->post(route('app.finance.adjustments.review', $adj2), ['decision' => 'approve'])->assertSessionHas('error');

        // Periode ditutup → tidak bisa mencatat pembayaran bertanggal di periode itu
        $last = now()->subMonth();
        $this->actingAs($admin)->post(route('app.finance.periods.close'), ['period' => $last->format('Y-m')])->assertSessionHas('success');
        $this->actingAs($billing)->post(route('app.payments.store'), ['customer_id' => $this->customer->id, 'amount' => '1000', 'method' => 'cash', 'paid_at' => $last->format('Y-m-15 10:00')])->assertSessionHas('error');
        $this->actingAs($admin)->get(route('app.finance.index'))->assertOk();
    }

    public function test_backup_test_restore_and_restore(): void
    {
        $admin = $this->tenantUser($this->tenant);
        $this->actingAs($admin)->post(route('app.backups.store'))->assertSessionHas('success');
        $artifact = BackupArtifact::firstOrFail();
        $this->assertSame(1, $artifact->table_counts['customers']);

        $this->actingAs($admin)->post(route('app.backups.test', $artifact))->assertSessionHas('success');
        $this->assertSame('passed', RestoreJob::first()->status);
        $this->actingAs($admin)->get(route('app.backups.index'))->assertOk();

        // Kerusakan data setelah backup
        $this->inTenant($this->tenant, fn () => Customer::first()->update(['name' => 'RUSAK']));

        auth()->guard('web')->logout();
        $super = $this->superAdmin();
        $this->actingAs($super)->post(route('admin.backups.restore', $artifact), ['confirm_code' => 'SALAH', 'reason' => 'x'])->assertSessionHasErrors('confirm_code');
        $this->actingAs($super)->post(route('admin.backups.restore', $artifact), ['confirm_code' => $this->tenant->code, 'reason' => 'Data pelanggan rusak'])->assertSessionHas('success');
        $this->assertSame('Pelanggan Keuangan', $this->inTenant($this->tenant, fn () => Customer::first()->name));
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => Invoice::count()));
        $this->assertDatabaseHas('backup_jobs', ['trigger' => 'pre_restore', 'status' => 'completed']);

        $this->actingAs($super)->get(route('admin.backups.index'))->assertOk();
        $this->actingAs($super)->get(route('admin.health'))->assertOk();

        // Tenant lain tidak bisa mengunduh backup
        $other = $this->makeTenant('ISP-Z');
        auth()->guard('web')->logout();
        $this->actingAs($this->tenantUser($other))->get(route('app.backups.download', $artifact))->assertNotFound();
    }
}
