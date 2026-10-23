<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\SaasPlan;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk mencoba aplikasi (opsional):  php artisan db:seed --class=DemoSeeder
 * Semua nama bersifat generik.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Tenant::where('code', 'ISP-DEMO')->exists()) {
            $this->command?->info('Tenant demo sudah ada.');

            return;
        }

        $tenant = app(TenantProvisioningService::class)->create([
            'name' => 'ISP Contoh', 'code' => 'ISP-DEMO', 'pic_name' => 'Nama PIC', 'whatsapp' => '081200000000',
            'email' => 'isp@example.com', 'address' => 'Alamat kantor ISP', 'province' => 'Provinsi', 'city' => 'Kota',
            'saas_plan_id' => SaasPlan::where('code', 'starter')->value('id'), 'period' => 'monthly', 'initial_status' => 'trial',
            'trial_days' => 30, 'admin_name' => 'Admin ISP Contoh', 'admin_email' => 'admin@isp-contoh.test',
        ]);

        app(TenantContext::class)->run($tenant->fresh(), function () {
            $p1 = ServicePackage::create(['code' => 'HOME10', 'name' => 'Home 10 Mbps', 'price' => 150000, 'download_mbps' => 10, 'upload_mbps' => 10, 'is_active' => true]);
            $p2 = ServicePackage::create(['code' => 'HOME20', 'name' => 'Home 20 Mbps', 'price' => 200000, 'download_mbps' => 20, 'upload_mbps' => 20, 'is_active' => true]);

            foreach (range(1, 5) as $i) {
                $c = new Customer([
                    'name' => 'Pelanggan Contoh '.$i, 'phone' => '0812000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'address' => 'Alamat pemasangan '.$i, 'ownership' => 'own', 'status' => 'active',
                ]);
                $c->customer_code = sprintf('PLG-%05d', $i);
                $c->save();
                $c->services()->create(['service_package_id' => $i % 2 ? $p1->id : $p2->id, 'status' => 'active', 'billing_day' => 1, 'due_days' => 10, 'started_at' => now()->startOfMonth()]);
            }
            \App\Models\TenantSequence::updateOrCreate(['tenant_id' => app(TenantContext::class)->id(), 'key' => 'customer'], ['value' => 5]);
        });

        $this->command?->info('Tenant demo dibuat: ISP-DEMO. Admin: admin@isp-contoh.test (aktivasi via link di halaman detail ISP).');
    }
}
