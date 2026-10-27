<?php

declare(strict_types=1);

namespace PathfinderMediaGroup\NovaChunkedFile;

use PathfinderMediaGroup\NovaChunkedFile\Console\PruneChunkedUploads;
use PathfinderMediaGroup\NovaChunkedFile\Http\Middleware\ResolveChunkedUploads;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chunked-file.php', 'chunked-file');
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            // The nova:api group is defined while Nova boots, so push afterwards.
            $this->app->make(Router::class)->pushMiddlewareToGroup('nova:api', ResolveChunkedUploads::class);

            $this->routes();
        });

        Nova::serving(function (ServingNova $event) {
            Nova::script('chunked-file', __DIR__.'/../dist/js/field.js');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([PruneChunkedUploads::class]);

            $this->publishes([
                __DIR__.'/../config/chunked-file.php' => config_path('chunked-file.php'),
            ], 'chunked-file-config');
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('chunked-file:prune')->hourly();
        });
    }

    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova:api'])
            ->prefix('nova-vendor/chunked-file')
            ->group(__DIR__.'/../routes/api.php');
    }
}
