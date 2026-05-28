<?php

use Rkdhatterwal\DecodoScraper\PayloadBuilder;
use Rkdhatterwal\DecodoScraper\DecodoClient;
use Rkdhatterwal\DecodoScraper\AsyncDecodoClient;
use Illuminate\Support\Facades\Http;

test('PayloadBuilder automatically sets domain for premium proxy pool (single URL)', function () {
    $builder = (new PayloadBuilder())
        ->url('https://www.google.fr/search')
        ->proxyPool('premium');

    $payload = $builder->build();

    expect($payload['domain'])->toBe('fr');
});

test('PayloadBuilder handles multi-part TLDs for premium proxy pool', function () {
    $builder = (new PayloadBuilder())
        ->url('https://www.google.co.uk/search')
        ->proxyPool('premium');

    $payload = $builder->build();

    expect($payload['domain'])->toBe('co.uk');
});

test('PayloadBuilder automatically sets domain for premium proxy pool (batch, same TLD)', function () {
    $builder = (new PayloadBuilder())
        ->proxyPool('premium');

    $payload = $builder->buildBatch([
        'https://www.google.com/a',
        'https://www.google.com/b'
    ]);

    expect($payload['domain'])->toBe('com');
});

test('PayloadBuilder omits domain for premium proxy pool (batch, different TLDs)', function () {
    $builder = (new PayloadBuilder())
        ->domain('com') // Set a default first
        ->proxyPool('premium');

    $payload = $builder->buildBatch([
        'https://www.google.com/a',
        'https://www.google.fr/b'
    ]);

    expect($payload)->not->toHaveKey('domain');
});

test('PayloadBuilder does not override domain for standard proxy pool', function () {
    // Note: Standard pool filtering actually removes 'domain' anyway,
    // but let's check the logic before filtering if possible or just check final result.
    $builder = (new PayloadBuilder())
        ->url('https://www.google.fr/search')
        ->domain('com')
        ->proxyPool('standard');

    $payload = $builder->build();

    // Standard pool only allows proxy_pool, url, headless, geo
    expect($payload)->not->toHaveKey('domain');
});

test('AsyncDecodoClient uses automatic domain detection', function () {
    Http::fake([
        '*' => Http::response(['id' => 'task_123', 'status' => 'pending', 'url' => 'https://google.it']),
    ]);

    $client = new AsyncDecodoClient(app(Illuminate\Http\Client\Factory::class), 'test-token', [
        'defaults' => ['proxy_pool' => 'premium', 'domain' => 'com']
    ]);

    $client->queueTask('https://www.google.it/search');

    Http::assertSent(function (Illuminate\Http\Client\Request $request) {
        return $request['domain'] === 'it';
    });
});

test('PayloadBuilder does not set domain when query is used instead of url', function () {
    $builder = (new PayloadBuilder())
        ->query('google search')
        ->proxyPool('premium');

    $payload = $builder->build();

    expect($payload)->not->toHaveKey('domain');
});
