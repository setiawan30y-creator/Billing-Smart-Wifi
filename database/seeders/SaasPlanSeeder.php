<?php

namespace Database\Seeders;

use App\Models\SaasPlan;
use Illuminate\Database\Seeder;

/**
 * Harga sesuai blueprint = USULAN AWAL. is_price_approved=false sehingga invoice SaaS
 * yang dihasilkan berlabel SIMULASI sampai Super Admin mengesahkan harga.
 */
class SaasPlanSeeder extends Seeder
{
    public function run(): void
    {
        $base = ['billing', 'payments_manual', 'whatsapp', 'network_registry'];
        $plans = [
            ['code' => 'starter', 'name' => 'Starter', 'price_monthly' => 99000, 'client_quota' => 100, 'router_quota' => 3, 'features' => $base, 'sort_order' => 1],
            ['code' => 'business', 'name' => 'Business', 'price_monthly' => 199000, 'client_quota' => 500, 'router_quota' => 10, 'features' => [...$base, 'payment_gateway', 'monitoring'], 'sort_order' => 2],
            ['code' => 'professional', 'name' => 'Professional', 'price_monthly' => 399000, 'client_quota' => 1500, 'router_quota' => 25, 'features' => [...$base, 'payment_gateway', 'monitoring', 'custom_domain', 'theme_studio'], 'sort_order' => 3],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'price_monthly' => null, 'client_quota' => null, 'router_quota' => 100, 'is_custom' => true, 'features' => array_keys(SaasPlan::FEATURES), 'sort_order' => 4],
        ];

        foreach ($plans as $p) {
            SaasPlan::firstOrCreate(['code' => $p['code']], $p + [
                'overage_client_price' => 300,
                'overage_router_price' => 15000,
                'is_active' => true,
                'is_price_approved' => false,
                'description' => 'Harga usulan awal blueprint — belum disahkan.',
            ]);
        }
    }
}
