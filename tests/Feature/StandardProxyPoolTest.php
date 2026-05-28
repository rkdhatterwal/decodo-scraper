<?php

use Rkdhatterwal\DecodoScraper\PayloadBuilder;
use Rkdhatterwal\DecodoScraper\DecodoClient;
use Rkdhatterwal\DecodoScraper\AsyncDecodoClient;
use Illuminate\Support\Facades\Http;

test('PayloadBuilder filters extra fields when proxy_pool is standard', function () {
    $builder = (new PayloadBuilder())
        ->url('https://example.com')
        ->proxyPool('standard')
        ->deviceType('mobile')
        ->markdown()
        ->geo('United States')
        ->headless('html');

    $payload = $builder->build();

    expect($payload)->toHaveKeys(['url', 'proxy_pool', 'geo', 'headless']);
    expect($payload)->not->toHaveKey('device_type');
    expect($payload)->not->toHaveKey('markdown');
    expect($payload['proxy_pool'])->toBe('standard');
});

test('PayloadBuilder allows http_method and payload when proxy_pool is standard and method is POST', function () {
    $builder = (new PayloadBuilder())
        ->url('https://example.com')
        ->proxyPool('standard')
        ->httpMethod('post')
        ->payload('some data');

    $payload = $builder->build();

    expect($payload)->toHaveKeys(['url', 'proxy_pool', 'http_method', 'payload']);
    expect($payload['http_method'])->toBe('post');
    expect($payload['payload'])->toBe(base64_encode('some data'));
});

test('PayloadBuilder still filters http_method when proxy_pool is standard and method is GET', function () {
    $builder = (new PayloadBuilder())
        ->url('https://example.com')
        ->proxyPool('standard')
        ->httpMethod('get');

    $payload = $builder->build();

    expect($payload)->toHaveKey('url');
    expect($payload)->toHaveKey('proxy_pool');
    expect($payload)->not->toHaveKey('http_method');
});

test('PayloadBuilder does not filter extra fields when proxy_pool is premium', function () {
    $builder = (new PayloadBuilder())
        ->url('https://example.com')
        ->proxyPool('premium')
        ->deviceType('mobile')
        ->markdown();

    $payload = $builder->build();

    expect($payload)->toHaveKeys(['url', 'proxy_pool', 'device_type', 'markdown']);
    expect($payload['proxy_pool'])->toBe('premium');
});

test('PayloadBuilder filters extra fields in buildBatch when proxy_pool is standard', function () {
    $builder = (new PayloadBuilder())
        ->proxyPool('standard')
        ->deviceType('mobile');

    $payload = $builder->buildBatch(['https://example.com']);

    expect($payload)->toHaveKeys(['url', 'proxy_pool']);
    expect($payload)->not->toHaveKey('device_type');
    expect($payload['url'])->toBe(['https://example.com']);
});

test('PayloadBuilder allows http_method and payload in buildBatch when proxy_pool is standard and method is POST', function () {
    $builder = (new PayloadBuilder())
        ->proxyPool('standard')
        ->httpMethod('post')
        ->payload('batch data');

    $payload = $builder->buildBatch(['https://example.com']);

    expect($payload)->toHaveKeys(['url', 'proxy_pool', 'http_method', 'payload']);
    expect($payload['url'])->toBe(['https://example.com']);
    expect($payload['http_method'])->toBe('post');
    expect($payload['payload'])->toBe(base64_encode('batch data'));
});

test('AsyncDecodoClient filters callback_url when proxy_pool is standard', function () {
    Http::fake([
        '*' => Http::response(['id' => 'task_123', 'status' => 'pending', 'url' => 'https://example.com']),
    ]);

    $client = new AsyncDecodoClient(app(Illuminate\Http\Client\Factory::class), 'test-token', [
        'webhook' => ['auto_inject_callback' => true]
    ]);

    // We need to mock the route if it's used
    Illuminate\Support\Facades\Route::shouldReceive('has')->andReturn(true);

    $client->queueTask('https://example.com', [
        'proxy_pool' => 'standard'
    ], 'https://my-webhook.com');

    Http::assertSent(function (Illuminate\Http\Client\Request $request) {
        $data = $request->data();
        return $data['proxy_pool'] === 'standard' 
            && !isset($data['callback_url'])
            && isset($data['url']);
    });
});

test('AsyncDecodoClient filters callback_url in queueBatch when proxy_pool is standard', function () {
    Http::fake([
        '*' => Http::response([
            'id' => 'batch_123', 
            'status' => 'pending', 
            'tasks' => [
                ['id' => 't1', 'status' => 'pending', 'url' => 'https://example.com']
            ]
        ]),
    ]);

    $client = new AsyncDecodoClient(app(Illuminate\Http\Client\Factory::class), 'test-token');

    $client->queueBatch(['https://example.com'], [
        'proxy_pool' => 'standard',
        'device_type' => 'mobile'
    ], 'https://my-webhook.com');

    Http::assertSent(function (Illuminate\Http\Client\Request $request) {
        $data = $request->data();
        return $data['proxy_pool'] === 'standard' 
            && !isset($data['callback_url'])
            && !isset($data['device_type'])
            && is_array($data['url']);
    });
});
