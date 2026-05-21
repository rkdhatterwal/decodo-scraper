<?php

use Rkdhatterwal\DecodoScraper\PayloadBuilder;
use Rkdhatterwal\DecodoScraper\Exceptions\DecodoException;

describe('PayloadBuilder', function () {

    // -------------------------------------------------------------------------
    // Required fields
    // -------------------------------------------------------------------------

    it('requires a url or query before building', function () {
        expect(fn () => (new PayloadBuilder())->build())
            ->toThrow(\InvalidArgumentException::class, 'url or query');
    });

    it('builds a simple url payload', function () {
        $payload = (new PayloadBuilder())->url('https://example.com')->build();

        expect($payload)->toHaveKey('url', 'https://example.com')
            ->and($payload)->not->toHaveKey('query');
    });

    it('builds a query payload', function () {
        $payload = (new PayloadBuilder())->query('iphone 15')->build();

        expect($payload)->toHaveKey('query', 'iphone 15')
            ->and($payload)->not->toHaveKey('url');
    });

    it('throws on an invalid url', function () {
        expect(fn () => (new PayloadBuilder())->url('not-a-url'))
            ->toThrow(DecodoException::class, 'invalid');
    });

    it('throws on an empty query', function () {
        expect(fn () => (new PayloadBuilder())->query('   '))
            ->toThrow(\InvalidArgumentException::class);
    });

    // -------------------------------------------------------------------------
    // Optional parameters
    // -------------------------------------------------------------------------

    it('sets headless mode to html', function () {
        $payload = (new PayloadBuilder())->url('https://example.com')->headless('html')->build();
        expect($payload['headless'])->toBe('html');
    });

    it('sets headless mode to png (screenshot)', function () {
        $payload = (new PayloadBuilder())->url('https://example.com')->headless('png')->build();
        expect($payload['headless'])->toBe('png');
    });

    it('rejects an invalid headless value', function () {
        expect(fn () => (new PayloadBuilder())->headless('video'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('sets geo and locale', function () {
        $payload = (new PayloadBuilder())
            ->url('https://example.com')
            ->geo('United States')
            ->locale('en-US')
            ->build();

        expect($payload['geo'])->toBe('United States')
            ->and($payload['locale'])->toBe('en-US');
    });

    it('sets device_type', function () {
        $payload = (new PayloadBuilder())->url('https://example.com')->deviceType('mobile')->build();
        expect($payload['device_type'])->toBe('mobile');
    });

    it('rejects an invalid device_type', function () {
        expect(fn () => (new PayloadBuilder())->deviceType('smartwatch'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('sets proxy_pool to standard', function () {
        $payload = (new PayloadBuilder())->url('https://example.com')->proxyPool('standard')->build();
        expect($payload['proxy_pool'])->toBe('standard');
    });

    it('rejects an invalid proxy_pool', function () {
        expect(fn () => (new PayloadBuilder())->proxyPool('ultra'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('sets parse to true', function () {
        $payload = (new PayloadBuilder())->url('https://example.com')->parse()->build();
        expect($payload['parse'])->toBeTrue();
    });

    it('sets target', function () {
        $payload = (new PayloadBuilder())->url('https://example.com')->target('google_search')->build();
        expect($payload['target'])->toBe('google_search');
    });

    it('sets markdown to true', function () {
        $payload = (new PayloadBuilder())->url('https://example.com')->markdown()->build();
        expect($payload['markdown'])->toBeTrue();
    });

    it('sets successful_status_codes', function () {
        $payload = (new PayloadBuilder())->url('https://example.com')->successfulStatusCodes([401, 404])->build();
        expect($payload['successful_status_codes'])->toBe([401, 404]);
    });

    it('rejects invalid status codes', function () {
        expect(fn () => (new PayloadBuilder())->successfulStatusCodes([99]))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('encodes the post payload to base64', function () {
        $body    = '{"foo":"bar"}';
        $payload = (new PayloadBuilder())
            ->url('https://example.com')
            ->httpMethod('post')
            ->payload($body)
            ->build();

        expect($payload['payload'])->toBe(base64_encode($body))
            ->and($payload['http_method'])->toBe('post');
    });

    it('requires a payload body when http_method is post', function () {
        expect(fn () => (new PayloadBuilder())
            ->url('https://example.com')
            ->httpMethod('post')
            ->build()
        )->toThrow(\InvalidArgumentException::class, 'payload body');
    });

    it('sets callback_url', function () {
        $payload = (new PayloadBuilder())
            ->url('https://example.com')
            ->callbackUrl('https://my.site/callback')
            ->build();

        expect($payload['callback_url'])->toBe('https://my.site/callback');
    });

    it('throws on an invalid callback_url', function () {
        expect(fn () => (new PayloadBuilder())->callbackUrl('not-a-url'))
            ->toThrow(DecodoException::class, 'invalid');
    });

    it('sets passthrough', function () {
        $payload = (new PayloadBuilder())
            ->url('https://example.com')
            ->passthrough('my-secret-token')
            ->build();

        expect($payload['passthrough'])->toBe('my-secret-token');
    });

    // -------------------------------------------------------------------------
    // buildBatch
    // -------------------------------------------------------------------------

    it('builds a batch payload with url as array', function () {
        $urls = ['https://a.com', 'https://b.com'];
        $payload = (new PayloadBuilder())->buildBatch($urls);

        expect($payload['url'])->toBe($urls)
            ->and($payload)->not->toHaveKey('query');
    });

    it('throws on empty batch', function () {
        expect(fn () => (new PayloadBuilder())->buildBatch([]))
            ->toThrow(\InvalidArgumentException::class, 'at least one URL');
    });

    it('throws when a batch url is invalid', function () {
        expect(fn () => (new PayloadBuilder())->buildBatch(['https://valid.com', 'bad-url']))
            ->toThrow(DecodoException::class, 'invalid');
    });

    // -------------------------------------------------------------------------
    // fromDefaults
    // -------------------------------------------------------------------------

    it('merges defaults and overrides correctly', function () {
        $builder = PayloadBuilder::fromDefaults(
            ['geo' => 'Germany', 'headless' => 'html'],
            ['geo' => 'France'],  // override
        );

        $payload = $builder->url('https://example.com')->build();

        expect($payload['geo'])->toBe('France')     // override wins
            ->and($payload['headless'])->toBe('html'); // default kept
    });

    it('skips null and false defaults', function () {
        $builder = PayloadBuilder::fromDefaults(['geo' => null, 'markdown' => false]);
        $payload = $builder->url('https://example.com')->build();

        expect($payload)->not->toHaveKey('geo')
            ->and($payload)->not->toHaveKey('markdown');
    });
});
