<?php

use Illuminate\Support\Facades\Http;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

function faultedTask(array $attrs = []): DecodoTask
{
    return DecodoTask::create(array_merge([
        'decodo_task_id' => uniqid('task-'),
        'url'            => 'https://example.com',
        'status'         => 'faulted',
        'queued_at'      => now()->subHour(),
        'completed_at'   => now()->subHour(),
    ], $attrs));
}

describe('decodo:retry', function () {

    beforeEach(fn () => Http::preventStrayRequests());

    it('re-queues faulted tasks and updates their status to pending', function () {
        Http::fake([
            '*/task' => Http::response([
                'id' => 'new-task-id', 'status' => 'pending',
                'url' => 'https://example.com', 'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(), 'device_type' => 'desktop',
                'parse' => false, 'force_headers' => false, 'force_cookies' => false,
                'domain' => 'com', 'http_method' => 'get', 'headers' => [], 'cookies' => [],
                'successful_status_codes' => [],
            ]),
        ]);

        $task = faultedTask();

        $this->artisan('decodo:retry')->assertSuccessful();

        $task->refresh();
        expect($task->status)->toBe('pending')
            ->and($task->decodo_task_id)->toBe('new-task-id')
            ->and($task->result_content)->toBeNull()
            ->and($task->completed_at)->toBeNull();
    });

    it('retries only tasks matching the --status option', function () {
        Http::fake(['*/task' => Http::response([
            'id' => 'new-pending-id', 'status' => 'pending', 'url' => '',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
            'device_type' => 'desktop', 'parse' => false, 'force_headers' => false,
            'force_cookies' => false, 'domain' => 'com', 'http_method' => 'get',
            'headers' => [], 'cookies' => [], 'successful_status_codes' => [],
        ])]);

        $stuckPending = DecodoTask::create([
            'decodo_task_id' => 'stuck-001', 'url' => 'https://example.com',
            'status' => 'pending', 'queued_at' => now()->subDays(5),
        ]);
        $faulted = faultedTask(['decodo_task_id' => 'fault-001']);

        $this->artisan('decodo:retry', ['--status' => 'pending'])->assertSuccessful();

        $stuckPending->refresh();
        $faulted->refresh();

        expect($stuckPending->decodo_task_id)->toBe('new-pending-id') // retried
            ->and($faulted->status)->toBe('faulted'); // not touched
    });

    it('retries a specific task by --id', function () {
        Http::fake(['*/task' => Http::response([
            'id' => 'retried-specific', 'status' => 'pending', 'url' => 'https://example.com',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
            'device_type' => 'desktop', 'parse' => false, 'force_headers' => false,
            'force_cookies' => false, 'domain' => 'com', 'http_method' => 'get',
            'headers' => [], 'cookies' => [], 'successful_status_codes' => [],
        ])]);

        $task = faultedTask(['decodo_task_id' => 'target-task-001']);
        $other = faultedTask(['decodo_task_id' => 'other-task-002']);

        $this->artisan('decodo:retry', ['--id' => 'target-task-001'])->assertSuccessful();

        expect($task->fresh()->decodo_task_id)->toBe('retried-specific')
            ->and($other->fresh()->decodo_task_id)->toBe('other-task-002'); // untouched
    });

    it('respects the --limit option', function () {
        Http::fake(['*/task' => Http::response([
            'id' => uniqid('new-'), 'status' => 'pending', 'url' => '',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
            'device_type' => 'desktop', 'parse' => false, 'force_headers' => false,
            'force_cookies' => false, 'domain' => 'com', 'http_method' => 'get',
            'headers' => [], 'cookies' => [], 'successful_status_codes' => [],
        ])]);

        faultedTask(); faultedTask(); faultedTask();

        $this->artisan('decodo:retry', ['--limit' => 1])->assertSuccessful();

        // Only 1 task should have been updated.
        expect(DecodoTask::where('status', 'pending')->count())->toBe(1)
            ->and(DecodoTask::where('status', 'faulted')->count())->toBe(2);
    });

    it('makes no changes in --dry-run mode', function () {
        $task = faultedTask(['decodo_task_id' => 'dry-task-001']);

        $this->artisan('decodo:retry', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        expect($task->fresh()->status)->toBe('faulted'); // unchanged
    });

    it('shows info when no tasks match', function () {
        $this->artisan('decodo:retry')
            ->expectsOutputToContain('No tasks matched')
            ->assertSuccessful();
    });

    it('rejects an invalid --status value', function () {
        $this->artisan('decodo:retry', ['--status' => 'invalid'])
            ->expectsOutputToContain('Invalid --status')
            ->assertSuccessful();
    });
});
