<?php

namespace App\Providers;

use App\Contracts\WhatsAppGateway;
use App\Integrations\WhatsApp\HttpWhatsAppGateway;
use App\Integrations\WhatsApp\LogWhatsAppGateway;
use App\Models\User;
use App\Support\Permissions;
use App\Support\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);

        $this->app->bind(WhatsAppGateway::class, function () {
            return match (config('almara.whatsapp.driver')) {
                'http' => new HttpWhatsAppGateway(config('almara.whatsapp.http')),
                default => new LogWhatsAppGateway,
            };
        });
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        // Permission ditegakkan di backend: semua ability yang terdaftar di katalog dicek via role.
        Gate::before(function (User $user, string $ability) {
            if (! array_key_exists($ability, Permissions::ALL)) {
                return null;
            }

            return $user->is_active && $user->hasPermission($ability);
        });

        View::composer('layouts.app', function ($view) {
            $view->with('currentTenant', app(TenantContext::class)->tenant());
        });
    }
}
