<?php

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Rkdhatterwal\DecodoScraper\DecodoClient;
use Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult;
use Rkdhatterwal\DecodoScraper\Exceptions\DecodoException;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fakeResult(array $overrides = []): array
{
    return array_merge([
        'content'     => '<html><body>Hello</body></html>',
        'status_code' => 200,
        'url'         => 'https://example.com',
        'task_id'     => 'task-001',
        'created_at'  => '2025-01-01 09:00:00',
        'updated_at'  => '2025-01-01 09:00:03',
    ], $overrides);
}

function makeClient(string $token = 'test-token', array $config = []): DecodoClient
{
    return new DecodoClient(app(HttpFactory::class), $token, $config);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('DecodoClient', function () {

    beforeEach(fn () => Http::preventStrayRequests());

    it('throws when token is empty', function () {
        expect(fn () => makeClient(''))->toThrow(DecodoException::class);
    });

    it('scrapes a URL and returns a ScrapeResult', function () {
        Http::fake([
            '*/scrape' => Http::response(['results' => [fakeResult()]]),
        ]);

        $result = makeClient()->scrape('https://example.com');

        expect($result)->toBeInstanceOf(ScrapeResult::class)
            ->and($result->statusCode)->toBe(200)
            ->and($result->content)->toContain('Hello');
    });

    it('throws DecodoException on an invalid URL', function () {
        expect(fn () => makeClient()->scrape('not-a-url'))
            ->toThrow(DecodoException::class, 'invalid');
    });

    it('throws DecodoException when the API returns a non-2xx status', function () {
        Http::fake([
            '*/scrape' => Http::response('Unauthorized', 401),
        ]);

        expect(fn () => makeClient()->scrape('https://example.com'))
            ->toThrow(DecodoException::class, '401');
    });

    it('throws DecodoException when results are empty', function () {
        Http::fake([
            '*/scrape' => Http::response(['results' => []]),
        ]);

        expect(fn () => makeClient()->scrape('https://example.com'))
            ->toThrow(DecodoException::class);
    });

    it('scrapes multiple URLs and returns a collection', function () {
        Http::fake([
            '*/scrape' => Http::response([
                'results' => [
                    fakeResult(['url' => 'https://example.com']),
                    fakeResult(['url' => 'https://another.com']),
                ],
            ]),
        ]);

        $results = makeClient()->scrapeMany([
            'https://example.com',
            'https://another.com',
        ]);

        expect($results)->toHaveCount(2);
    });

    it('enables JS rendering via scrapeWithJs', function () {
        Http::fake([
            '*/scrape' => Http::response(['results' => [fakeResult()]]),
        ]);

        $result = makeClient()->scrapeWithJs('https://example.com');

        Http::assertSent(function ($request) {
            return $request->data()['headless'] === 'html';
        });

        expect($result)->toBeInstanceOf(ScrapeResult::class);
    });

    it('sends geo parameter via scrapeFromGeo', function () {
        Http::fake([
            '*/scrape' => Http::response(['results' => [fakeResult()]]),
        ]);

        makeClient()->scrapeFromGeo('https://example.com', 'us');

        Http::assertSent(function ($request) {
            return $request->data()['geo'] === 'us';
        });
    });

    it('merges default options from config', function () {
        Http::fake([
            '*/scrape' => Http::response(['results' => [fakeResult()]]),
        ]);

        makeClient('test-token', ['defaults' => ['geo' => 'gb']])
            ->scrape('https://example.com');

        Http::assertSent(function ($request) {
            return $request->data()['geo'] === 'gb';
        });
    });

    it('allows per-request options to override defaults', function () {
        Http::fake([
            '*/scrape' => Http::response(['results' => [fakeResult()]]),
        ]);

        makeClient('test-token', ['defaults' => ['geo' => 'gb']])
            ->scrape('https://example.com', ['geo' => 'us']);

        Http::assertSent(function ($request) {
            return $request->data()['geo'] === 'us';
        });
    });
});
