<?php

use Rkdhatterwal\DecodoScraper\Models\DecodoBatch;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

describe('DecodoBatch model', function () {

    it('recalculates status as done when all tasks are done', function () {
        $batch = DecodoBatch::create(['total_tasks' => 2, 'status' => 'pending', 'started_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'ta', 'decodo_batch_id' => $batch->id, 'status' => 'done', 'queued_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'tb', 'decodo_batch_id' => $batch->id, 'status' => 'done', 'queued_at' => now()]);

        $batch->recalculateStatus();
        $batch->refresh();

        expect($batch->status)->toBe('done')
            ->and($batch->completed_at)->not->toBeNull();
    });

    it('recalculates status as faulted when all tasks faulted', function () {
        $batch = DecodoBatch::create(['total_tasks' => 2, 'status' => 'pending', 'started_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'tc', 'decodo_batch_id' => $batch->id, 'status' => 'faulted', 'queued_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'td', 'decodo_batch_id' => $batch->id, 'status' => 'faulted', 'queued_at' => now()]);

        $batch->recalculateStatus();
        $batch->refresh();

        expect($batch->status)->toBe('faulted');
    });

    it('recalculates status as partial on mixed outcomes', function () {
        $batch = DecodoBatch::create(['total_tasks' => 2, 'status' => 'pending', 'started_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'te', 'decodo_batch_id' => $batch->id, 'status' => 'done',    'queued_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'tf', 'decodo_batch_id' => $batch->id, 'status' => 'faulted', 'queued_at' => now()]);

        $batch->recalculateStatus();
        $batch->refresh();

        expect($batch->status)->toBe('partial');
    });

    it('does not update status while tasks are still pending', function () {
        $batch = DecodoBatch::create(['total_tasks' => 2, 'status' => 'pending', 'started_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'tg', 'decodo_batch_id' => $batch->id, 'status' => 'done',    'queued_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'th', 'decodo_batch_id' => $batch->id, 'status' => 'pending', 'queued_at' => now()]);

        $batch->recalculateStatus();
        $batch->refresh();

        expect($batch->status)->toBe('pending');
    });

    it('counts pending, done, and faulted tasks correctly', function () {
        $batch = DecodoBatch::create(['total_tasks' => 3, 'status' => 'pending', 'started_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'ti', 'decodo_batch_id' => $batch->id, 'status' => 'pending', 'queued_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'tj', 'decodo_batch_id' => $batch->id, 'status' => 'done',    'queued_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'tk', 'decodo_batch_id' => $batch->id, 'status' => 'faulted', 'queued_at' => now()]);

        expect($batch->pendingCount())->toBe(1)
            ->and($batch->doneCount())->toBe(1)
            ->and($batch->faultedCount())->toBe(1);
    });

    it('exposes scoped query helpers', function () {
        DecodoBatch::create(['total_tasks' => 1, 'status' => 'pending',  'started_at' => now()]);
        DecodoBatch::create(['total_tasks' => 1, 'status' => 'done',     'started_at' => now()]);
        DecodoBatch::create(['total_tasks' => 1, 'status' => 'faulted',  'started_at' => now()]);
        DecodoBatch::create(['total_tasks' => 1, 'status' => 'partial',  'started_at' => now()]);

        expect(DecodoBatch::pending()->count())->toBe(1)
            ->and(DecodoBatch::done()->count())->toBe(1)
            ->and(DecodoBatch::faulted()->count())->toBe(1)
            ->and(DecodoBatch::partial()->count())->toBe(1);
    });
});

describe('DecodoTask model', function () {

    it('marks task as done with content and status code', function () {
        $task = DecodoTask::create([
            'decodo_task_id' => 'task-mark-done',
            'status'         => 'pending',
            'queued_at'      => now(),
        ]);

        $task->markDone('<p>hello</p>', 200, ['id' => 'task-mark-done', 'status' => 'done']);
        $task->refresh();

        expect($task->status)->toBe('done')
            ->and($task->result_content)->toBe('<p>hello</p>')
            ->and($task->result_status_code)->toBe(200)
            ->and($task->completed_at)->not->toBeNull();
    });

    it('marks task as faulted', function () {
        $task = DecodoTask::create([
            'decodo_task_id' => 'task-mark-fault',
            'status'         => 'pending',
            'queued_at'      => now(),
        ]);

        $task->markFaulted(['id' => 'task-mark-fault', 'status' => 'faulted']);
        $task->refresh();

        expect($task->status)->toBe('faulted')
            ->and($task->completed_at)->not->toBeNull();
    });

    it('scopes standalone tasks correctly', function () {
        $batch = DecodoBatch::create(['total_tasks' => 1, 'status' => 'pending', 'started_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'standalone-1', 'status' => 'pending', 'queued_at' => now()]);
        DecodoTask::create(['decodo_task_id' => 'batched-1', 'decodo_batch_id' => $batch->id, 'status' => 'pending', 'queued_at' => now()]);

        expect(DecodoTask::standalone()->count())->toBe(1)
            ->and(DecodoTask::standalone()->first()->decodo_task_id)->toBe('standalone-1');
    });
});
