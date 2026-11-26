<?php

namespace App\Providers;

use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Scoped: one instance per request/job, so business overrides are memoised but never stale.
        $this->app->scoped(\App\Services\Access\ModuleService::class);
        // Assigned-property limit for the signed-in team member, switched on by business.access.
        $this->app->scoped(\App\Services\Access\PropertyAccess::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    // public function boot()
    // {

    //     //
    // }

    public function boot(UrlGenerator $url)
    {
        // Commission settings on agent profiles become agreements; switched-off agents stop holding units.
        \App\Models\Agent::observe(\App\Observers\AgentCommissionObserver::class);

        if ($this->app->environment('production')) {
            $url->forceScheme('https');
        }

        if (!function_exists('asset_versioned')) {
            $this->app->singleton('asset_versioned', function ($app) {
                return function ($path) {
                    try {
                        $publicPath = public_path($path);
                        $version = file_exists($publicPath) ? filemtime($publicPath) : time();
                        return asset($path) . '?v=' . $version;
                    } catch (\Exception $e) {
                        return asset($path);
                    }
                };
            });
        }

        // Team permissions: @businessCan('tenant_management', 'create') ... @endbusinessCan (owners always pass)
        \Illuminate\Support\Facades\Blade::if('businessCan', function (string $group, string $action = 'view') {
            $user = auth()->user();
            return $user && app(\App\Services\Access\BusinessPermissionService::class)->can($user, $group, $action);
        });

        // Account owner only (modules, payment methods, account deletion)
        \Illuminate\Support\Facades\Blade::if('businessOwner', function () {
            return auth()->check() && auth()->user()->isBusinessOwner();
        });

        // Optional Erty modules: @module('listings') ... @endmodule
        \Illuminate\Support\Facades\Blade::if('module', function (string $module) {
            return app(\App\Services\Access\ModuleService::class)->enabled($module);
        });
    }
}
