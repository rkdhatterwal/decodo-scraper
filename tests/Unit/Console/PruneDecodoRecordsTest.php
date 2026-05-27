<?php

use Illuminate\Support\Carbon;
use Rkdhatterwal\DecodoScraper\Models\DecodoBatch;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function doneTask(array $attrs = []): DecodoTask
{
    return DecodoTask::create(array_merge([
        'decodo_task_id'    => uniqid('task-'),
        'status'            => 'done',
        'result_content'    => '<html>big content</html>',
        'result_status_code'=> 200,
        'queued_at'         => now()->subDays(40),
        'completed_at'      => now()->subDays(40),
    ], $attrs));
}

function pendingTask(array $attrs = []): DecodoTask
{
    return DecodoTask::create(array_merge([
        'decodo_task_id' => uniqid('task-'),
        'status'         => 'pending',
        'queued_at'      => now()->subDays(5),
    ], $attrs));
}

function terminalBatch(array $attrs = []): DecodoBatch
{
    return DecodoBatch::create(array_merge([
        'total_tasks'  => 1,
        'status'       => 'done',
        'started_at'   => now()->subDays(70),
        'completed_at' => now()->subDays(70),
    ], $attrs));
}

function defaultPruningConfig(): void
{
    config([
        'decodo.pruning.content_days'       => 7,
        'decodo.pruning.tasks_days'         => 30,
        'decodo.pruning.pending_tasks_days' => 3,
        'decodo.pruning.batches_days'       => 60,
    ]);
}

// ---------------------------------------------------------------------------
// Content nullification
// ---------------------------------------------------------------------------

describe('decodo:prune — result_content nullification', function () {

    beforeEach(fn () => defaultPruningConfig());

    it('nullifies result_content older than content_days', function () {
        $old   = doneTask(['completed_at' => now()->subDays(10)]);
        $fresh = doneTask(['completed_at' => now()->subDays(3)]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect($old->fresh()->result_content)->toBeNull()       // nullified ✓
            ->and($fresh->fresh()->result_content)->not->toBeNull(); // kept ✓
    });

    it('preserves the task row after nullifying content', function () {
        $task = doneTask(['completed_at' => now()->subDays(10)]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect(DecodoTask::find($task->id))->not->toBeNull();
    });

    it('does not nullify content on pending tasks', function () {
        $task = DecodoTask::create([
            'decodo_task_id'  => uniqid('task-'),
            'status'          => 'pending',
            'result_content'  => '<html>content</html>',
            'queued_at'       => now()->subDays(1),
        ]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect($task->fresh()->result_content)->not->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Task deletion
// ---------------------------------------------------------------------------

describe('decodo:prune — task deletion', function () {

    beforeEach(fn () => defaultPruningConfig());

    it('deletes done tasks older than tasks_days', function () {
        $old   = doneTask(['completed_at' => now()->subDays(35)]);
        $fresh = doneTask(['completed_at' => now()->subDays(10)]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect(DecodoTask::find($old->id))->toBeNull()           // deleted ✓
            ->and(DecodoTask::find($fresh->id))->not->toBeNull(); // kept ✓
    });

    it('deletes faulted tasks older than tasks_days', function () {
        $task = doneTask([
            'status'       => 'faulted',
            'completed_at' => now()->subDays(35),
        ]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect(DecodoTask::find($task->id))->toBeNull();
    });

    it('deletes stuck-pending tasks older than pending_tasks_days', function () {
        $stale = pendingTask(['queued_at' => now()->subDays(5)]);
        $fresh = pendingTask(['queued_at' => now()->subDays(1)]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect(DecodoTask::find($stale->id))->toBeNull()          // deleted ✓
            ->and(DecodoTask::find($fresh->id))->not->toBeNull();  // kept ✓
    });

    it('does not delete recently completed tasks', function () {
        $task = doneTask(['completed_at' => now()->subDays(5)]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect(DecodoTask::find($task->id))->not->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Batch deletion
// ---------------------------------------------------------------------------

describe('decodo:prune — batch deletion', function () {

    beforeEach(fn () => defaultPruningConfig());

    it('deletes terminal batches older than batches_days', function () {
        $old   = terminalBatch(['completed_at' => now()->subDays(65)]);
        $fresh = terminalBatch(['completed_at' => now()->subDays(10)]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect(DecodoBatch::find($old->id))->toBeNull()           // deleted ✓
            ->and(DecodoBatch::find($fresh->id))->not->toBeNull(); // kept ✓
    });

    it('does not delete pending batches regardless of age', function () {
        $batch = DecodoBatch::create([
            'total_tasks' => 2,
            'status'      => 'pending',
            'started_at'  => now()->subDays(90),
        ]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect(DecodoBatch::find($batch->id))->not->toBeNull();
    });

    it('deletes partial batches older than batches_days', function () {
        $batch = terminalBatch([
            'status'       => 'partial',
            'completed_at' => now()->subDays(65),
        ]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect(DecodoBatch::find($batch->id))->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Dry-run
// ---------------------------------------------------------------------------

describe('decodo:prune --dry-run', function () {

    beforeEach(fn () => defaultPruningConfig());

    it('makes no changes when --dry-run is passed', function () {
        $task  = doneTask(['completed_at' => now()->subDays(40)]);
        $batch = terminalBatch(['completed_at' => now()->subDays(70)]);

        $this->artisan('decodo:prune', ['--dry-run' => true])->assertSuccessful();

        // Nothing was touched.
        expect(DecodoTask::find($task->id))->not->toBeNull()
            ->and($task->fresh()->result_content)->not->toBeNull()
            ->and(DecodoBatch::find($batch->id))->not->toBeNull();
    });

    it('outputs DRY RUN warning', function () {
        $this->artisan('decodo:prune', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// Custom retention windows
// ---------------------------------------------------------------------------

describe('decodo:prune — custom retention config', function () {

    it('respects a shorter content_days window', function () {
        config(['decodo.pruning.content_days' => 2]);
        config(['decodo.pruning.tasks_days'   => 90]);
        config(['decodo.pruning.pending_tasks_days' => 90]);
        config(['decodo.pruning.batches_days' => 90]);

        $task = doneTask(['completed_at' => now()->subDays(3)]);

        $this->artisan('decodo:prune')->assertSuccessful();

        expect($task->fresh()->result_content)->toBeNull(); // window = 2d, task is 3d old
    });

    it('respects a longer tasks_days window', function () {
        config(['decodo.pruning.content_days'       => 1]);
        config(['decodo.pruning.tasks_days'         => 90]);
        config(['decodo.pruning.pending_tasks_days' => 90]);
        config(['decodo.pruning.batches_days'       => 90]);

        $task = doneTask(['completed_at' => now()->subDays(40)]);

        $this->artisan('decodo:prune')->assertSuccessful();

        // Row still exists — window is 90d, task is only 40d old.
        expect(DecodoTask::find($task->id))->not->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Chunk option
// ---------------------------------------------------------------------------

describe('decodo:prune --chunk', function () {

    it('accepts a custom chunk size without errors', function () {
        defaultPruningConfig();
        doneTask(['completed_at' => now()->subDays(40)]);
        doneTask(['completed_at' => now()->subDays(40)]);

        $this->artisan('decodo:prune', ['--chunk' => 1])->assertSuccessful();

        expect(DecodoTask::where('status', 'done')->count())->toBe(0);
    });
});
