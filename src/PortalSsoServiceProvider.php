<?php

namespace KominfoKabtang\PortalSso;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use KominfoKabtang\PortalSso\Contracts\SettingsResolver;
use KominfoKabtang\PortalSso\Http\Middleware\RedirectToPortalWhenRequired;

class PortalSsoServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/portal-sso.php', 'portal-sso');

        $this->app->bind(SettingsResolver::class, function ($app) {
            return $app->make(config('portal-sso.settings_resolver'));
        });

        $this->app->singleton(PortalSsoClient::class);
    }

    public function boot()
    {
        $this->app->make(Router::class)->aliasMiddleware('portal-sso.required', RedirectToPortalWhenRequired::class);

        if (config('portal-sso.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'portal-sso');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/portal-sso.php' => config_path('portal-sso.php'),
            ], 'portal-sso-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/portal-sso'),
            ], 'portal-sso-views');
        }
    }
}
