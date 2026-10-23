<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * Konteks tenant aktif. Ditentukan di server (user login / job / command),
 * tidak pernah dari tenant_id yang dikirim client.
 * @2026-Alifiandra
 */
class TenantContext
{
    protected ?int $tenantId = null;

    protected bool $explicit = false;

    public function set(Tenant|int|null $tenant): void
    {
        $this->tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $this->explicit = true;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->explicit = false;
    }

    public function id(): ?int
    {
        if ($this->explicit) {
            return $this->tenantId;
        }

        // Fallback lazy: ambil dari user yang sedang login (guard web).
        if (Auth::hasUser() || Auth::check()) {
            return Auth::user()->tenant_id;
        }

        return null;
    }

    public function tenant(): ?Tenant
    {
        $id = $this->id();

        return $id ? Tenant::find($id) : null;
    }

    /**
     * Jalankan callback di dalam konteks tenant tertentu (untuk command/job).
     */
    public function run(Tenant|int $tenant, callable $callback): mixed
    {
        $prevId = $this->tenantId;
        $prevExplicit = $this->explicit;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->tenantId = $prevId;
            $this->explicit = $prevExplicit;
        }
    }
}
