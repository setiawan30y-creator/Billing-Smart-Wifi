<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\CustomerImportService;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImportBatch implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $tenantId, public int $batchId) {}

    public function uniqueId(): string
    {
        return 'import-batch:'.$this->batchId;
    }

    public function handle(CustomerImportService $service): void
    {
        $batch = app(TenantContext::class)->run($this->tenantId, fn () => ImportBatch::find($this->batchId));
        if ($batch) {
            $service->execute($batch);
        }
    }

    public function failed(Throwable $e): void
    {
        app(TenantContext::class)->run($this->tenantId, function () use ($e) {
            ImportBatch::whereKey($this->batchId)->update(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 500)]);
        });
    }
}
