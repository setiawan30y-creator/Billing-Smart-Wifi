<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin() && $user->is_active, 403, 'Khusus Super Admin.');

        return $next($request);
    }
}
