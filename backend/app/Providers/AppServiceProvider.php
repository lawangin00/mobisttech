<?php

namespace App\Providers;

use App\Support\LocalEnvironmentGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment(['local', 'testing'])) {
            LocalEnvironmentGuard::validate(
                config('database.connections.'.config('database.default')),
                config('database.redis.default'),
                (bool) config('foundation.external_integrations_enabled'),
            );
            Http::preventStrayRequests();
        }
    }
}
