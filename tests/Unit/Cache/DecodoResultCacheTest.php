<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Rkdhatterwal\DecodoScraper\Cache\DecodoResultCache;
use Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult;

function fakeResultsResponse(): array
{
    return [
        'results' => [[
            'content'     => '<h1>Cached</h1>',
            'status_code' => 200,
            'url'         => 'https://example.com',
            'task_id'     => 'cache-task-001',
            'created_at'  => now()->toDateTimeString(),
            'updated_at'  => now()->toDateTimeString(),
        ]],
    ];
}

describe('DecodoResultCache', function () {

    beforeEach(function () {
        Http::preventStrayRequests();
        Cache::flush();
        config(['decodo.cache.enabled' => true, 'decodo.cache.ttl' => 3600, 'decodo.cache.prefix' => 'test_decodo']);
    });

    it('fetches from API on first call and stores in cache', function () {
        Http::fake(['*/task/cache-task-001/results' => Http::response(fakeResultsResponse())]);

        $cache = app(DecodoResultCache::class);
        $results = $cache->remember('cache-task-001');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(ScrapeResult::class)
            ->and($results->first()->content)->toBe('<h1>Cached</h1>');

        // Cache should be populated.
        expect($cache->has('cache-task-001'))->toBeTrue();
    });

    it('returns cached results on second call without hitting the API', function () {
        Http::fake(['*/task/cache-task-001/results' => Http::response(fakeResultsResponse())]);

        $cache = app(DecodoResultCache::class);
        $cache->remember('cache-task-001'); // prime cache
        $cache->remember('cache-task-001'); // should use cache

        Http::assertSentCount(1); // only ONE real request
    });

    it('bypasses and refreshes cache on fresh()', function () {
        Http::fake(['*/task/cache-task-001/results' => Http::response(fakeResultsResponse())]);

        $cache = app(DecodoResultCache::class);
        $cache->remember('cache-task-001'); // prime
        $cache->fresh('cache-task-001');    // force refresh

        Http::assertSentCount(2); // two real requests
    });

    it('removes cached entry on forget()', function () {
        Http::fake(['*/task/cache-task-001/results' => Http::response(fakeResultsResponse())]);

        $cache = app(DecodoResultCache::class);
        $cache->remember('cache-task-001');
        expect($cache->has('cache-task-001'))->toBeTrue();

        $cache->forget('cache-task-001');
        expect($cache->has('cache-task-001'))->toBeFalse();
    });

    it('skips cache when disabled in config', function () {
        config(['decodo.cache.enabled' => false]);
        Http::fake(['*/task/cache-task-001/results' => Http::response(fakeResultsResponse())]);

        $cache = new DecodoResultCache(app('decodo.async'));
        $cache->remember('cache-task-001');
        $cache->remember('cache-task-001');

        Http::assertSentCount(2); // both hit the API
        expect($cache->has('cache-task-001'))->toBeFalse();
    });
});
