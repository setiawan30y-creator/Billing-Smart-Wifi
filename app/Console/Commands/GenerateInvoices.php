<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    protected $signature = 'billing:generate-invoices {--period= : YYYY-MM, default bulan berjalan per timezone tenant} {--tenant= : kode tenant} {--no-notify}';

    protected $description = 'Generate invoice bulanan pelanggan (idempoten, aman dijalankan berulang)';

    public function handle(InvoiceService $service): int
    {
        $tenants = Tenant::whereIn('status', ['trial', 'active'])
            ->when($this->option('tenant'), fn ($q, $c) => $q->where('code', $c))->get();

        foreach ($tenants as $tenant) {
            $period = $this->option('period') ?: now($tenant->timezone)->format('Y-m');
            $r = $service->generateForPeriod($tenant, $period, ! $this->option('no-notify'));
            $this->line("{$tenant->code} {$period}: {$r['created']} dibuat, {$r['skipped']} dilewati");
        }

        return self::SUCCESS;
    }
}
