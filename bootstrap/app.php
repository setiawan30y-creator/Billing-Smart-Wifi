<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'tenant' => \App\Http\Middleware\EnsureTenantUser::class,
            'portal.tenant' => \App\Http\Middleware\PortalTenant::class,
        ]);
        $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class]);
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->redirectGuestsTo(fn (Request $r) => $r->is('portal/*') && $r->route('tenantCode')
            ? route('portal.login', $r->route('tenantCode')) : route('login'));
        $middleware->redirectUsersTo(function (Request $r) {
            if ($r->is('portal/*') && $r->route('tenantCode')) {
                return route('portal.home', $r->route('tenantCode'));
            }

            return $r->user()?->isSuperAdmin() ? route('admin.dashboard') : route('app.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('webhooks/*') || $request->expectsJson(),
        );
    })->create();
