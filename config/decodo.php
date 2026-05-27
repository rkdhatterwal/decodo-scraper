<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Decodo API Token
    |--------------------------------------------------------------------------
    |
    | Your Decodo Basic Auth token from https://dashboard.decodo.com.
    | Authorization: Basic {token}
    |
    */

    'token' => env('DECODO_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Real-time API Base URL (v2)
    |--------------------------------------------------------------------------
    */

    'base_url' => env('DECODO_BASE_URL', 'https://scraper-api.decodo.com/v2'),

    /*
    |--------------------------------------------------------------------------
    | Async API Base URL (v3)
    |--------------------------------------------------------------------------
    */

    'async_base_url' => env('DECODO_ASYNC_BASE_URL', 'https://scraper-api.decodo.com/v3'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Real-time: Decodo keeps connections open up to 150s for heavy pages.
    | Async: only needs to wait for queue acknowledgement (~30s).
    |
    */

    'timeout' => env('DECODO_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Batch Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Decodo enforces 1 request per second on the /task/batch endpoint.
    | The package enforces this automatically in-process. Increase the value
    | if you're seeing 429 responses due to clock skew or network jitter.
    |
    | Unit: milliseconds. Default: 1000 (1 second).
    |
    */

    'batch_rate_limit_ms' => env('DECODO_BATCH_RATE_LIMIT_MS', 1_000),

    /*
    |--------------------------------------------------------------------------
    | Database Persistence
    |--------------------------------------------------------------------------
    |
    | When enabled, async tasks and batches are persisted to the database.
    | Run: php artisan vendor:publish --tag=decodo-migrations
    |      php artisan migrate
    |
    */

    'database' => [
        'enabled' => env('DECODO_DB_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Result Cache
    |--------------------------------------------------------------------------
    |
    | Cache completed task results to avoid redundant Decodo API calls.
    | Results for a "done" task are immutable, making them ideal for caching.
    |
    | enabled — Toggle caching on/off.
    | store   — Laravel cache store to use (null = default store).
    | ttl     — Seconds to keep results cached. Default: 82800 (23 hours),
    |            just under Decodo's 24-hour result-expiry window.
    | prefix  — Cache key prefix. Change if you have key conflicts.
    |
    */

    'cache' => [
        'enabled' => env('DECODO_CACHE_ENABLED', true),
        'store'   => env('DECODO_CACHE_STORE', null),
        'ttl'     => env('DECODO_CACHE_TTL', 82_800),
        'prefix'  => env('DECODO_CACHE_PREFIX', 'decodo_result'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | enabled               — Register webhook routes automatically.
    | path                  — URL prefix for webhook routes (no leading slash).
    | verify_passthrough     — Verify the passthrough token on every callback.
    | passthrough_secret     — Secret echoed back by Decodo for verification.
    | auto_inject_callback   — Automatically set the callback_url on every
    |                          queueTask / queueBatch call to the package's
    |                          own webhook route. Set to false to manage
    |                          callback URLs manually.
    |
    | Remember to exempt the webhook path from CSRF in:
    |   app/Http/Middleware/VerifyCsrfToken.php → $except = ['decodo/webhook/*']
    |
    */

    'webhook' => [
        'enabled'              => env('DECODO_WEBHOOK_ENABLED', true),
        'path'                 => env('DECODO_WEBHOOK_PATH', 'decodo/webhook'),
        'verify_passthrough'   => env('DECODO_WEBHOOK_VERIFY', true),
        'passthrough_secret'   => env('DECODO_WEBHOOK_SECRET'),
        'auto_inject_callback' => env('DECODO_WEBHOOK_AUTO_INJECT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | channel — The Laravel log channel to use for package log messages.
    |           null = use the application's default channel.
    |           Set to 'decodo' and add a matching channel to config/logging.php
    |           to isolate scraping activity in its own log file.
    |
    | Example logging.php channel entry:
    |
    |   'decodo' => [
    |       'driver' => 'daily',
    |       'path'   => storage_path('logs/decodo.log'),
    |       'level'  => 'debug',
    |       'days'   => 14,
    |   ],
    |
    */

    'logging' => [
        'channel' => env('DECODO_LOG_CHANNEL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning / Data Retention
    |--------------------------------------------------------------------------
    |
    | content_days       — Days after completion before result_content is
    |                      nullified (row kept, bulk HTML freed). Default: 7.
    |
    | tasks_days         — Days after completion before done/faulted task rows
    |                      are permanently deleted. Default: 30.
    |
    | pending_tasks_days — Days after queuing before a stuck-pending task is
    |                      considered abandoned and deleted. Default: 3.
    |
    | batches_days       — Days after completion before terminal batch rows
    |                      are permanently deleted. Default: 60.
    |
    | schedule_enabled   — Register the command in Laravel's scheduler.
    |
    | schedule_frequency — Scheduler frequency: 'daily', 'weekly', 'hourly',
    |                      'dailyAt:02:00', 'weeklyOn:1:03:00', etc.
    |
    */

    'pruning' => [
        'content_days'       => env('DECODO_PRUNE_CONTENT_DAYS', 7),
        'tasks_days'         => env('DECODO_PRUNE_TASKS_DAYS', 30),
        'pending_tasks_days' => env('DECODO_PRUNE_PENDING_DAYS', 3),
        'batches_days'       => env('DECODO_PRUNE_BATCHES_DAYS', 60),
        'schedule_enabled'   => env('DECODO_PRUNE_SCHEDULE', true),
        'schedule_frequency' => env('DECODO_PRUNE_FREQUENCY', 'daily'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Payload Parameters
    |--------------------------------------------------------------------------
    |
    | Merged into every request unless overridden per-call.
    | See: https://help.decodo.com/docs/web-scraping-api-parameters
    |
    */

    'defaults' => [
        'proxy_pool' => env('DECODO_PROXY_POOL', 'premium'),
        'headless' => env('DECODO_HEADLESS'),
        'geo' => env('DECODO_GEO'),
        'domain' => env('DECODO_DOMAIN', 'com'),
        'locale' => env('DECODO_LOCALE'),
        'device_type' => env('DECODO_DEVICE_TYPE', 'desktop'),
        'markdown' => env('DECODO_MARKDOWN', false),
        'parse' => env('DECODO_PARSE', false),
        'headers' => [],
        'cookies' => [],
    ],

];
