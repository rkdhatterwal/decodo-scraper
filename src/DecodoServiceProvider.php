<?php

namespace Rkdhatterwal\DecodoScraper;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Rkdhatterwal\DecodoScraper\Cache\DecodoResultCache;
use Rkdhatterwal\DecodoScraper\Console\Commands\DecodoStatus;
use Rkdhatterwal\DecodoScraper\Console\Commands\PruneDecodoRecords;
use Rkdhatterwal\DecodoScraper\Console\Commands\RetryDecodoTasks;
use Rkdhatterwal\DecodoScraper\Exceptions\DecodoException;

class DecodoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/decodo.php', 'decodo');

        // Real-time client (v2)
        $this->app->singleton(DecodoClient::class, function ($app) {
            $config = $app['config']['decodo'];
            $token  = $config['token'] ?? '';

            if (empty($token)) {
                throw DecodoException::missingToken();
            }

            return new DecodoClient(
                $app->make(Factory::class),
                $token,
                $config,
            );
        });

        // Async client (v3)
        $this->app->singleton(AsyncDecodoClient::class, function ($app) {
            $config = $app['config']['decodo'];
            $token  = $config['token'] ?? '';

            if (empty($token)) {
                throw DecodoException::missingToken();
            }

            return new AsyncDecodoClient(
                $app->make(Factory::class),
                $token,
                $config,
            );
        });

        // Result cache
        $this->app->singleton(DecodoResultCache::class, function ($app) {
            return new DecodoResultCache($app->make(AsyncDecodoClient::class));
        });

        $this->app->alias(DecodoClient::class,      'decodo');
        $this->app->alias(AsyncDecodoClient::class,  'decodo.async');
        $this->app->alias(DecodoResultCache::class,  'decodo.cache');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__ . '/../config/decodo.php' => config_path('decodo.php'),
            ], 'decodo-config');

            // Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'decodo-migrations');

            // Artisan commands
            $this->commands([
                PruneDecodoRecords::class,
                RetryDecodoTasks::class,
                DecodoStatus::class,
            ]);
        }

        // Webhook routes — registered automatically when enabled.
        if (config('decodo.webhook.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/decodo.php');
        }

        // Scheduler
        if (config('decodo.pruning.schedule_enabled', true)) {
            $this->registerPruneSchedule();
        }
    }

    // -------------------------------------------------------------------------
    // Scheduler
    // -------------------------------------------------------------------------

    private function registerPruneSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $frequency = config('decodo.pruning.schedule_frequency', 'daily');
            $event     = $schedule->command('decodo:prune')->withoutOverlapping();

            if (str_contains($frequency, ':')) {
                [$method, $args] = explode(':', $frequency, 2);
                $event->{$method}(...explode(':', $args));
            } else {
                $event->{$frequency}();
            }
        });
    }
}
