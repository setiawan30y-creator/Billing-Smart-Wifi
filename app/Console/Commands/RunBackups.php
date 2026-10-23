<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackups extends Command
{
    protected $signature = 'backups:run {--tenant=} {--prune}';

    protected $description = 'Backup terjadwal semua tenant aktif (+ hapus backup kedaluwarsa dengan --prune)';

    public function handle(BackupService $service): int
    {
        foreach (Tenant::whereIn('status', ['trial', 'active', 'suspended'])->when($this->option('tenant'), fn ($q, $c) => $q->where('code', $c))->get() as $t) {
            $job = $service->backup($t, 'scheduled');
            $this->line("{$t->code}: {$job->status}".($job->error ? ' — '.$job->error : ''));
        }
        if ($this->option('prune')) {
            $this->line($service->prune().' backup kedaluwarsa dihapus.');
        }

        return self::SUCCESS;
    }
}
