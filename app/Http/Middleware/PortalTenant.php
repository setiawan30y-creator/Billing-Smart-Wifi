<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Portal pelanggan: tenant ditentukan dari kode ISP di URL (/portal/{kode}/...).
 * Akun pelanggan yang login wajib milik tenant tersebut.
 */
class PortalTenant
{
    public function handle(Request $request, Closure $next)
    {
        $code = strtoupper((string) $request->route('tenantCode'));
        $tenant = Tenant::where('code', $code)->first();
        abort_unless($tenant, 404, 'Portal ISP tidak ditemukan.');

        app(TenantContext::class)->set($tenant);
        $request->attributes->set('portalTenant', $tenant);
        view()->share('portalTenant', $tenant);

        if (! $tenant->isOperational()) {
            return response()->view('portal.unavailable', ['tenant' => $tenant], 503);
        }

        $account = Auth::guard('customer')->user();
        if ($account && ($account->tenant_id !== $tenant->id || ! $account->is_active)) {
            Auth::guard('customer')->logout();

            return redirect()->route('portal.login', $tenant->code);
        }

        return $next($request);
    }
}
