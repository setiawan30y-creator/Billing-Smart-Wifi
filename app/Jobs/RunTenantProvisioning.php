<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunTenantProvisioning implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $tenantId, public string $targetStatus = 'trial') {}

    /** Identitas job: retry tidak membuat provisioning ganda. */
    public function uniqueId(): string
    {
        return 'tenant-provisioning:'.$this->tenantId;
    }

    public function handle(TenantProvisioningService $service): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant) {
            $service->run($tenant, $this->targetStatus);
        }
    }
}
