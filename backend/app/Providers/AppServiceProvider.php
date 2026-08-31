<?php

namespace App\Providers;

use App\Identity\IdentityUserProvider;
use App\Support\LocalEnvironmentGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
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
        Auth::provider('identity', fn ($app, $config) => new IdentityUserProvider($app['hash'], $config['model']));
        RateLimiter::for('identity', function (Request $request) {
            $realm = $request->attributes->get('identity_realm');
            $identity = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by($realm.'|'.$request->path().'|'.hash('sha256', $identity.'|'.$request->ip())),
                Limit::perMinute(30)->by($realm.'|'.$request->ip()),
            ];
        });
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
