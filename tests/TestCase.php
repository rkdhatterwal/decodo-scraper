<?php

namespace Rkdhatterwal\DecodoScraper\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rkdhatterwal\DecodoScraper\DecodoServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DecodoServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Setup default config for tests
        $app['config']->set('decodo.token', 'test-token');
        $app['config']->set('decodo.database.enabled', true);
        $app['config']->set('decodo.webhook.enabled', true);
        $app['config']->set('decodo.webhook.verify_passthrough', false);
        $app['config']->set('decodo.webhook.passthrough_secret', null);
    }
}
