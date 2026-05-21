<?php

use Illuminate\Support\Facades\Route;
use Rkdhatterwal\DecodoScraper\Http\Controllers\DecodoWebhookController;
use Rkdhatterwal\DecodoScraper\Http\Middleware\VerifyDecodoWebhook;

/*
|--------------------------------------------------------------------------
| Decodo Webhook Routes
|--------------------------------------------------------------------------
|
| These routes are registered automatically by the DecodoServiceProvider
| when 'webhook.enabled' is true in config/decodo.php.
|
| Decodo will POST to these URLs once an async task completes:
|
|   Task callback:   POST {prefix}/task
|   Batch callback:  POST {prefix}/batch
|
| The prefix defaults to "decodo/webhook" and is configurable via:
|   config('decodo.webhook.path')
|
| IMPORTANT: Add these URLs to Laravel's CSRF exception list in
| app/Http/Middleware/VerifyCsrfToken.php:
|
|   protected $except = [
|       'decodo/webhook/*',
|   ];
|
*/

Route::prefix(config('decodo.webhook.path', 'decodo/webhook'))
    ->middleware(array_filter([
        'api',
        config('decodo.webhook.verify_passthrough') ? VerifyDecodoWebhook::class : null,
    ]))
    ->group(function () {
        Route::post('task',  [DecodoWebhookController::class, 'handleTask'])->name('decodo.webhook.task');
        Route::post('batch', [DecodoWebhookController::class, 'handleBatch'])->name('decodo.webhook.batch');
    });
