<?php

use Orchestra\Testbench\TestCase;
use Rkdhatterwal\DecodoScraper\DecodoServiceProvider;

uses(TestCase::class)
    ->beforeEach(function () {
        // Run package migrations before each test that needs the database.
        // loadMigrationsFrom is available on $this inside a Testbench TestCase.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Default config so tests don't need real credentials.
        config([
            'decodo.token'                   => 'test-token',
            'decodo.database.enabled'        => true,
            'decodo.webhook.enabled'         => true,
            'decodo.webhook.verify_passthrough' => false,
            'decodo.webhook.passthrough_secret' => null,
        ]);
    })
    ->in('Unit', 'Feature');

// ---------------------------------------------------------------------------
// Register the package providers for every test
// ---------------------------------------------------------------------------

function getPackageProviders($app): array
{
    return [DecodoServiceProvider::class];
}
