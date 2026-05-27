<?php

use Illuminate\Support\Facades\Http;
use Rkdhatterwal\DecodoScraper\BatchRateLimiter;

describe('Auto callback URL injection', function () {

    beforeEach(function () {
        Http::preventStrayRequests();
        config(['decodo.webhook.enabled' => true]);
        config(['decodo.webhook.auto_inject_callback' => true]);
    });

    it('auto-injects the task webhook URL when no callbackUrl is provided', function () {
        Http::fake(['*/task' => Http::response([
            'id' => 'auto-cb-task', 'status' => 'pending',
            'url' => 'https://example.com',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
            'device_type' => 'desktop', 'parse' => false, 'force_headers' => false,
            'force_cookies' => false, 'domain' => 'com', 'http_method' => 'get',
            'headers' => [], 'cookies' => [], 'successful_status_codes' => [],
        ])]);

        app('decodo.async')->queueTask('https://example.com');

        Http::assertSent(function ($request) {
            $data = $request->data();
            return isset($data['callback_url'])
                && str_contains($data['callback_url'], 'decodo/webhook/task');
        });
    });

    it('uses explicitly provided callbackUrl over the auto-injected one', function () {
        Http::fake(['*/task' => Http::response([
            'id' => 'override-cb-task', 'status' => 'pending',
            'url' => 'https://example.com',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
            'device_type' => 'desktop', 'parse' => false, 'force_headers' => false,
            'force_cookies' => false, 'domain' => 'com', 'http_method' => 'get',
            'headers' => [], 'cookies' => [], 'successful_status_codes' => [],
        ])]);

        app('decodo.async')->queueTask(
            url:         'https://example.com',
            callbackUrl: 'https://custom.site/my-hook',
        );

        Http::assertSent(function ($request) {
            return $request->data()['callback_url'] === 'https://custom.site/my-hook';
        });
    });

    it('skips injection when auto_inject_callback is disabled', function () {
        config(['decodo.webhook.auto_inject_callback' => false]);
        app()->forgetInstance(\Rkdhatterwal\DecodoScraper\AsyncDecodoClient::class);
        app()->forgetInstance('decodo.async');

        Http::fake(['*/task' => Http::response([
            'id' => 'no-cb-task', 'status' => 'pending', 'url' => 'https://example.com',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
            'device_type' => 'desktop', 'parse' => false, 'force_headers' => false,
            'force_cookies' => false, 'domain' => 'com', 'http_method' => 'get',
            'headers' => [], 'cookies' => [], 'successful_status_codes' => [],
        ])]);

        app('decodo.async')->queueTask('https://example.com');

        Http::assertSent(function ($request) {
            return ! isset($request->data()['callback_url']);
        });
    });

    it('auto-injects batch webhook URL for queueBatch', function () {
        Http::fake(['*/task/batch' => Http::response([[
            'id' => 'batch-auto-cb', 'status' => 'pending', 'url' => 'https://a.com',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
            'device_type' => 'desktop', 'parse' => false, 'force_headers' => false,
            'force_cookies' => false, 'domain' => 'com', 'http_method' => 'get',
            'headers' => [], 'cookies' => [], 'successful_status_codes' => [],
        ]])]);

        BatchRateLimiter::reset();
        app('decodo.async')->queueBatch(['https://a.com']);

        Http::assertSent(function ($request) {
            $data = $request->data();
            return isset($data['callback_url'])
                && str_contains($data['callback_url'], 'decodo/webhook/task');
        });
    });
});

describe('BatchRateLimiter', function () {

    beforeEach(fn () => BatchRateLimiter::reset());

    it('does not sleep on the first call', function () {
        $start = microtime(true);
        BatchRateLimiter::throttle(500);
        $elapsed = (microtime(true) - $start) * 1000;

        expect($elapsed)->toBeLessThan(50); // first call should be near-instant
    });

    it('sleeps the remaining interval when called too quickly', function () {
        BatchRateLimiter::throttle(200); // prime
        $start = microtime(true);
        BatchRateLimiter::throttle(200); // should sleep ~200ms
        $elapsed = (microtime(true) - $start) * 1000;

        expect($elapsed)->toBeGreaterThanOrEqual(150); // allow 50ms jitter
    });

    it('does not sleep when the interval has already passed', function () {
        BatchRateLimiter::throttle(1); // prime with 1ms interval
        usleep(5_000);                 // wait 5ms — well past the 1ms limit

        $start = microtime(true);
        BatchRateLimiter::throttle(1);
        $elapsed = (microtime(true) - $start) * 1000;

        expect($elapsed)->toBeLessThan(20); // no sleep needed
    });

    it('can be reset between calls', function () {
        BatchRateLimiter::throttle(500); // prime
        BatchRateLimiter::reset();

        $start = microtime(true);
        BatchRateLimiter::throttle(500); // treated as first call again
        $elapsed = (microtime(true) - $start) * 1000;

        expect($elapsed)->toBeLessThan(50);
    });
});

describe('DecodoTaskExpired event', function () {

    it('is fired during prune for stuck-pending tasks', function () {
        config([
            'decodo.pruning.content_days'       => 1,
            'decodo.pruning.tasks_days'         => 1,
            'decodo.pruning.pending_tasks_days' => 1,
            'decodo.pruning.batches_days'       => 1,
        ]);

        \Illuminate\Support\Facades\Event::fake();

        \Rkdhatterwal\DecodoScraper\Models\DecodoTask::create([
            'decodo_task_id' => 'expired-pending-001',
            'url'            => 'https://example.com',
            'status'         => 'pending',
            'queued_at'      => now()->subDays(5),
        ]);

        $this->artisan('decodo:prune')->assertSuccessful();

        \Illuminate\Support\Facades\Event::assertDispatched(
            \Rkdhatterwal\DecodoScraper\Events\DecodoTaskExpired::class,
            function ($event) {
                return $event->task->decodo_task_id === 'expired-pending-001'
                    && $event->reason === 'pending_timeout';
            }
        );
    });

    it('is not fired for terminal (done/faulted) task deletions', function () {
        config([
            'decodo.pruning.content_days'       => 1,
            'decodo.pruning.tasks_days'         => 1,
            'decodo.pruning.pending_tasks_days' => 90,
            'decodo.pruning.batches_days'       => 1,
        ]);

        \Illuminate\Support\Facades\Event::fake();

        \Rkdhatterwal\DecodoScraper\Models\DecodoTask::create([
            'decodo_task_id' => 'old-done-task',
            'url'            => 'https://example.com',
            'status'         => 'done',
            'queued_at'      => now()->subDays(5),
            'completed_at'   => now()->subDays(5),
        ]);

        $this->artisan('decodo:prune')->assertSuccessful();

        \Illuminate\Support\Facades\Event::assertNotDispatched(
            \Rkdhatterwal\DecodoScraper\Events\DecodoTaskExpired::class
        );
    });
});
