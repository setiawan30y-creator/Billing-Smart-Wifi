<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\TenantContext;

abstract class Controller
{
    protected function tenant(): Tenant
    {
        $tenant = app(TenantContext::class)->tenant();
        abort_unless($tenant, 403);

        return $tenant;
    }

    /** Pesan error ramah dari exception domain (InvalidArgumentException). */
    protected function fail(\Throwable $e)
    {
        return back()->withInput()->with('error', $e->getMessage());
    }
}
