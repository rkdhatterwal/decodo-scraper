<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Rkdhatterwal\DecodoScraper\Events\DecodoBatchCompleted;
use Rkdhatterwal\DecodoScraper\Events\DecodoTaskCompleted;
use Rkdhatterwal\DecodoScraper\Events\DecodoTaskFaulted;
use Rkdhatterwal\DecodoScraper\Models\DecodoBatch;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

// ---------------------------------------------------------------------------
// Test setup helpers
// ---------------------------------------------------------------------------

function createTask(array $attrs = []): DecodoTask
{
    return DecodoTask::create(array_merge([
        'decodo_task_id' => 'task-001',
        'url'            => 'https://example.com',
        'status'         => 'pending',
        'queued_at'      => now(),
    ], $attrs));
}

function createBatch(array $attrs = []): DecodoBatch
{
    return DecodoBatch::create(array_merge([
        'total_tasks' => 2,
        'status'      => 'pending',
        'passthrough' => 'batch-secret',
        'started_at'  => now(),
    ], $attrs));
}

function taskCallbackPayload(array $overrides = []): array
{
    return array_merge([
        'id'          => 'task-001',
        'status'      => 'done',
        'url'         => 'https://example.com',
        'passthrough' => 'my-secret',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Task webhook tests
// ---------------------------------------------------------------------------

describe('DecodoWebhookController — task', function () {

    beforeEach(function () {
        Event::fake();
        Http::preventStrayRequests();
    });

    it('returns 422 when id is missing', function () {
        $this->postJson(route('decodo.webhook.task'), ['status' => 'done'])
            ->assertStatus(422);
    });

    it('acknowledges unknown task IDs gracefully', function () {
        $this->postJson(route('decodo.webhook.task'), taskCallbackPayload())
            ->assertOk()
            ->assertJson(['message' => 'Task not tracked locally.']);

        Event::assertNotDispatched(DecodoTaskCompleted::class);
    });

    it('marks a task done and fires DecodoTaskCompleted', function () {
        Http::fake([
            '*/task/task-001/results' => Http::response([
                'results' => [[
                    'content'     => '<h1>Hello</h1>',
                    'status_code' => 200,
                    'url'         => 'https://example.com',
                    'task_id'     => 'task-001',
                    'created_at'  => now()->toDateTimeString(),
                    'updated_at'  => now()->toDateTimeString(),
                ]],
            ]),
        ]);

        $task = createTask();

        $this->postJson(route('decodo.webhook.task'), taskCallbackPayload(['status' => 'done']))
            ->assertOk();

        $task->refresh();

        expect($task->status)->toBe('done')
            ->and($task->result_content)->toBe('<h1>Hello</h1>')
            ->and($task->result_status_code)->toBe(200)
            ->and($task->completed_at)->not->toBeNull();

        Event::assertDispatched(DecodoTaskCompleted::class, function ($event) use ($task) {
            return $event->task->id === $task->id;
        });
    });

    it('marks a task faulted and fires DecodoTaskFaulted', function () {
        $task = createTask();

        $this->postJson(route('decodo.webhook.task'), taskCallbackPayload(['status' => 'faulted']))
            ->assertOk();

        $task->refresh();

        expect($task->status)->toBe('faulted')
            ->and($task->completed_at)->not->toBeNull();

        Event::assertDispatched(DecodoTaskFaulted::class);
        Event::assertNotDispatched(DecodoTaskCompleted::class);
    });

    it('recalculates batch status and fires DecodoBatchCompleted when last task finishes', function () {
        Http::fake([
            '*/task/task-001/results' => Http::response([
                'results' => [[
                    'content' => '<p>Done</p>', 'status_code' => 200,
                    'url' => 'https://example.com', 'task_id' => 'task-001',
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ]],
            ]),
        ]);

        $batch = createBatch(['total_tasks' => 1]);
        createTask(['decodo_task_id' => 'task-001', 'decodo_batch_id' => $batch->id]);

        $this->postJson(route('decodo.webhook.task'), taskCallbackPayload(['status' => 'done']))
            ->assertOk();

        $batch->refresh();

        expect($batch->status)->toBe('done')
            ->and($batch->completed_at)->not->toBeNull();

        Event::assertDispatched(DecodoBatchCompleted::class, function ($event) use ($batch) {
            return $event->batch->id === $batch->id;
        });
    });

    it('sets batch status to partial when some tasks fault', function () {
        Http::fake([
            '*/task/task-002/results' => Http::response([
                'results' => [[
                    'content' => '<p>OK</p>', 'status_code' => 200,
                    'url' => 'https://b.com', 'task_id' => 'task-002',
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ]],
            ]),
        ]);

        $batch = createBatch(['total_tasks' => 2]);
        createTask(['decodo_task_id' => 'task-001', 'decodo_batch_id' => $batch->id, 'status' => 'faulted']);
        createTask(['decodo_task_id' => 'task-002', 'decodo_batch_id' => $batch->id]);

        // Complete the second task — now all are in a terminal state.
        $this->postJson(route('decodo.webhook.task'), taskCallbackPayload([
            'id'     => 'task-002',
            'status' => 'done',
        ]))->assertOk();

        $batch->refresh();

        expect($batch->status)->toBe('partial');
        Event::assertDispatched(DecodoBatchCompleted::class);
    });
});

// ---------------------------------------------------------------------------
// Batch webhook tests
// ---------------------------------------------------------------------------

describe('DecodoWebhookController — batch', function () {

    beforeEach(fn () => Event::fake());

    it('acknowledges when batch is not found by passthrough', function () {
        $this->postJson(route('decodo.webhook.batch'), ['passthrough' => 'unknown'])
            ->assertOk()
            ->assertJson(['message' => 'Batch not tracked locally.']);

        Event::assertNotDispatched(DecodoBatchCompleted::class);
    });

    it('fires DecodoBatchCompleted when all tasks are done', function () {
        $batch = createBatch(['passthrough' => 'batch-secret']);
        createTask(['decodo_task_id' => 'task-a', 'decodo_batch_id' => $batch->id, 'status' => 'done']);
        createTask(['decodo_task_id' => 'task-b', 'decodo_batch_id' => $batch->id, 'status' => 'done']);

        $this->postJson(route('decodo.webhook.batch'), ['passthrough' => 'batch-secret'])
            ->assertOk();

        $batch->refresh();
        expect($batch->status)->toBe('done');
        Event::assertDispatched(DecodoBatchCompleted::class);
    });

    it('does not fire DecodoBatchCompleted while tasks are still pending', function () {
        $batch = createBatch(['passthrough' => 'batch-secret']);
        createTask(['decodo_task_id' => 'task-a', 'decodo_batch_id' => $batch->id, 'status' => 'done']);
        createTask(['decodo_task_id' => 'task-b', 'decodo_batch_id' => $batch->id, 'status' => 'pending']);

        $this->postJson(route('decodo.webhook.batch'), ['passthrough' => 'batch-secret'])
            ->assertOk();

        Event::assertNotDispatched(DecodoBatchCompleted::class);
    });
});

// ---------------------------------------------------------------------------
// Middleware tests
// ---------------------------------------------------------------------------

describe('VerifyDecodoWebhook middleware', function () {

    it('blocks requests with wrong passthrough when verification is enabled', function () {
        config(['decodo.webhook.verify_passthrough' => true]);
        config(['decodo.webhook.passthrough_secret' => 'correct-secret']);

        $this->postJson(route('decodo.webhook.task'), ['id' => 'x', 'status' => 'done', 'passthrough' => 'wrong'])
            ->assertForbidden();
    });

    it('allows requests with correct passthrough', function () {
        config(['decodo.webhook.verify_passthrough' => true]);
        config(['decodo.webhook.passthrough_secret' => 'correct-secret']);

        $this->postJson(route('decodo.webhook.task'), [
            'id'          => 'task-999',
            'status'      => 'done',
            'passthrough' => 'correct-secret',
        ])->assertOk(); // task not found → graceful acknowledge, but not 403
    });

    it('skips verification when disabled', function () {
        config(['decodo.webhook.verify_passthrough' => false]);

        $this->postJson(route('decodo.webhook.task'), ['id' => 'x', 'status' => 'done'])
            ->assertOk();
    });
});
