<?php

use Rkdhatterwal\DecodoScraper\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(function () {
        // Run package migrations before each test that needs the database.
        // loadMigrationsFrom is available on $this inside a Testbench TestCase.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    })
    ->in('Unit', 'Feature');

