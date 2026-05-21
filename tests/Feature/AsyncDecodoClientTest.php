<?php

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Rkdhatterwal\DecodoScraper\AsyncDecodoClient;
use Rkdhatterwal\DecodoScraper\DTOs\BatchTaskResponse;
use Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult;
use Rkdhatterwal\DecodoScraper\DTOs\TaskResponse;
use Rkdhatterwal\DecodoScraper\Exceptions\DecodoException;
use Rkdhatterwal\DecodoScraper\PayloadBuilder;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fakeTask(array $overrides = []): array
{
    return array_merge([
        'id'                      => 'task-001',
        'status'                  => 'pending',
        'url'                     => 'https://example.com',
        'created_at'              => '2025-01-01 09:00:00',
        'updated_at'              => '2025-01-01 09:00:00',
        'target'                  => null,
        'query'                   => null,
        'device_type'             => 'desktop',
        'headless'                => null,
        'parse'                   => false,
        'force_headers'           => false,
        'force_cookies'           => false,
        'geo'                     => null,
        'locale'                  => null,
        'domain'                  => 'com',
        'http_method'             => 'get',
        'session_id'              => null,
        'headers'                 => [],
        'cookies'                 => [],
        'successful_status_codes' => [],
        'payload'                 => null,
        'callback_url'            => null,
        'passthrough'             => null,
    ], $overrides);
}

function fakeTaskResult(): array
{
    return [
        'content'     => '<html><body>Hello</body></html>',
        'status_code' => 200,
        'url'         => 'https://example.com',
        'task_id'     => 'task-001',
        'created_at'  => '2025-01-01 09:00:00',
        'updated_at'  => '2025-01-01 09:00:03',
    ];
}

function makeAsyncClient(string $token = 'test-token', array $config = []): AsyncDecodoClient
{
    return new AsyncDecodoClient(app(HttpFactory::class), $token, $config);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('AsyncDecodoClient', function () {

    beforeEach(fn () => Http::preventStrayRequests());

    it('throws when token is empty', function () {
        expect(fn () => makeAsyncClient(''))->toThrow(DecodoException::class);
    });

    // -------------------------------------------------------------------------
    // queueTask
    // -------------------------------------------------------------------------

    it('queues a single task and returns a TaskResponse', function () {
        Http::fake(['*/task' => Http::response(fakeTask())]);

        $response = makeAsyncClient()->queueTask('https://example.com');

        expect($response)->toBeInstanceOf(TaskResponse::class)
            ->and($response->id)->toBe('task-001')
            ->and($response->status)->toBe('pending')
            ->and($response->isPending())->toBeTrue();
    });

    it('includes callback_url in the task payload', function () {
        Http::fake(['*/task' => Http::response(fakeTask(['callback_url' => 'https://my.site/cb']))]);

        makeAsyncClient()->queueTask('https://example.com', [], 'https://my.site/cb');

        Http::assertSent(fn ($req) => $req->data()['callback_url'] === 'https://my.site/cb');
    });

    it('includes passthrough in the task payload', function () {
        Http::fake(['*/task' => Http::response(fakeTask())]);

        makeAsyncClient()->queueTask('https://example.com', [], null, 'my-secret');

        Http::assertSent(fn ($req) => $req->data()['passthrough'] === 'my-secret');
    });

    it('queues a task using a PayloadBuilder', function () {
        Http::fake(['*/task' => Http::response(fakeTask())]);

        $builder = (new PayloadBuilder())->url('https://example.com')->geo('United States');
        $response = makeAsyncClient()->queueTaskWithBuilder($builder);

        expect($response)->toBeInstanceOf(TaskResponse::class);
        Http::assertSent(fn ($req) => $req->data()['geo'] === 'United States');
    });

    it('throws DecodoException on task queue failure', function () {
        Http::fake(['*/task' => Http::response('Unauthorized', 401)]);

        expect(fn () => makeAsyncClient()->queueTask('https://example.com'))
            ->toThrow(DecodoException::class, '401');
    });

    // -------------------------------------------------------------------------
    // queueBatch
    // -------------------------------------------------------------------------

    it('queues a batch and returns a BatchTaskResponse', function () {
        Http::fake([
            '*/task/batch' => Http::response([fakeTask(['id' => 't1']), fakeTask(['id' => 't2'])]),
        ]);

        $response = makeAsyncClient()->queueBatch([
            'https://example.com',
            'https://another.com',
        ]);

        expect($response)->toBeInstanceOf(BatchTaskResponse::class)
            ->and($response->count())->toBe(2)
            ->and($response->ids()->toArray())->toBe(['t1', 't2']);
    });

    it('sends urls as an array in the batch payload', function () {
        Http::fake(['*/task/batch' => Http::response([fakeTask()])]);

        makeAsyncClient()->queueBatch(['https://a.com', 'https://b.com']);

        Http::assertSent(fn ($req) => is_array($req->data()['url']) && count($req->data()['url']) === 2);
    });

    it('includes callback_url in the batch payload', function () {
        Http::fake(['*/task/batch' => Http::response([fakeTask()])]);

        makeAsyncClient()->queueBatch(['https://example.com'], [], 'https://cb.site/hook');

        Http::assertSent(fn ($req) => $req->data()['callback_url'] === 'https://cb.site/hook');
    });

    // -------------------------------------------------------------------------
    // getTaskStatus
    // -------------------------------------------------------------------------

    it('retrieves task status and returns pending TaskResponse', function () {
        Http::fake(['*/task/task-001' => Http::response(fakeTask(['status' => 'pending']))]);

        $status = makeAsyncClient()->getTaskStatus('task-001');

        expect($status->isPending())->toBeTrue()
            ->and($status->isDone())->toBeFalse();
    });

    it('retrieves done status correctly', function () {
        Http::fake(['*/task/task-001' => Http::response(fakeTask(['status' => 'done']))]);

        $status = makeAsyncClient()->getTaskStatus('task-001');

        expect($status->isDone())->toBeTrue();
    });

    it('throws on empty task id', function () {
        expect(fn () => makeAsyncClient()->getTaskStatus(''))
            ->toThrow(\InvalidArgumentException::class, 'Task ID');
    });

    // -------------------------------------------------------------------------
    // getTaskResults
    // -------------------------------------------------------------------------

    it('retrieves task results and returns a Collection of ScrapeResults', function () {
        Http::fake([
            '*/task/task-001/results' => Http::response(['results' => [fakeTaskResult()]]),
        ]);

        $results = makeAsyncClient()->getTaskResults('task-001');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(ScrapeResult::class)
            ->and($results->first()->statusCode)->toBe(200);
    });

    it('throws when results are empty', function () {
        Http::fake(['*/task/task-001/results' => Http::response(['results' => []])]);

        expect(fn () => makeAsyncClient()->getTaskResults('task-001'))
            ->toThrow(DecodoException::class);
    });

    it('retrieves only the first result via getFirstTaskResult', function () {
        Http::fake([
            '*/task/task-001/results' => Http::response(['results' => [fakeTaskResult()]]),
        ]);

        $result = makeAsyncClient()->getFirstTaskResult('task-001');

        expect($result)->toBeInstanceOf(ScrapeResult::class);
    });

    // -------------------------------------------------------------------------
    // pollUntilDone
    // -------------------------------------------------------------------------

    it('polls until done and returns results', function () {
        Http::fakeSequence()
            ->push(fakeTask(['status' => 'pending']))          // 1st poll: pending
            ->push(fakeTask(['status' => 'done']))             // 2nd poll: done
            ->push(['results' => [fakeTaskResult()]]);         // results fetch

        $results = makeAsyncClient()->pollUntilDone('task-001', intervalMs: 0);

        expect($results)->toHaveCount(1);
    });

    it('throws when a task faults during polling', function () {
        Http::fake(['*/task/task-001' => Http::response(fakeTask(['status' => 'faulted']))]);

        expect(fn () => makeAsyncClient()->pollUntilDone('task-001', intervalMs: 0))
            ->toThrow(DecodoException::class, 'faulted');
    });

    it('throws when max attempts are reached', function () {
        Http::fake(['*/task/task-001' => Http::response(fakeTask(['status' => 'pending']))]);

        expect(fn () => makeAsyncClient()->pollUntilDone('task-001', intervalMs: 0, maxAttempts: 2))
            ->toThrow(DecodoException::class, 'did not complete');
    });
});
