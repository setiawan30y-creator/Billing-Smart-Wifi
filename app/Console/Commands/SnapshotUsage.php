<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\UsageMeter;
use Illuminate\Console\Command;

class SnapshotUsage extends Command
{
    protected $signature = 'saas:snapshot-usage';

    protected $description = 'Catat snapshot pemakaian kuota (client aktif, layanan, router) per tenant';

    public function handle(): int
    {
        foreach (Tenant::whereIn('status', ['trial', 'active', 'suspended'])->get() as $tenant) {
            $r = UsageMeter::snapshot($tenant, now()->format('Y-m'));
            $this->line("{$tenant->code}: client {$r->active_clients}, layanan {$r->active_services}, router {$r->routers}");
        }

        return self::SUCCESS;
    }
}
