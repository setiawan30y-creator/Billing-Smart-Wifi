<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Area tenant: konteks tenant diambil dari user login (server-side).
 * Tenant yang ditangguhkan tidak dihapus datanya, tetapi aksesnya ditahan.
 */
class EnsureTenantUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->tenant_id && ! $user->is_super_admin, 403, 'Akun ini bukan akun ISP.');

        if (! $user->is_active) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda dinonaktifkan.']);
        }

        $tenant = $user->tenant;
        app(TenantContext::class)->set($tenant);

        if (! $tenant || ! $tenant->isOperational()) {
            return response()->view('errors.tenant-unavailable', ['tenant' => $tenant], 403);
        }

        return $next($request);
    }
}
