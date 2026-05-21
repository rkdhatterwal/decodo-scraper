<?php

namespace Rkdhatterwal\DecodoScraper\Console\Commands;

use Illuminate\Console\Command;
use Rkdhatterwal\DecodoScraper\Events\DecodoTaskExpired;
use Rkdhatterwal\DecodoScraper\Models\DecodoBatch;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Prune stale records from decodo_tasks and decodo_batches.
 *
 * Three independent retention windows are applied:
 *
 *  1. result_content nullified   — scrape HTML/Markdown set to null after
 *                                   `pruning.content_days` days (row kept).
 *
 *  2. Task rows deleted          — entire decodo_tasks row removed after
 *                                   `pruning.tasks_days` days (done/faulted) or
 *                                   `pruning.pending_tasks_days` (stuck pending).
 *                                   DecodoTaskExpired is fired before each pending
 *                                   task is deleted so listeners can react.
 *
 *  3. Batch rows deleted         — entire decodo_batches row removed after
 *                                   `pruning.batches_days` days.
 *
 * Usage:
 *   php artisan decodo:prune
 *   php artisan decodo:prune --dry-run
 *   php artisan decodo:prune --chunk=500
 */
class PruneDecodoRecords extends Command
{
    protected $signature = 'decodo:prune
                            {--dry-run   : Preview what would be pruned without making changes}
                            {--chunk=200 : Number of records to delete per query to avoid memory spikes}';

    protected $description = 'Prune stale Decodo task and batch records according to configured retention windows';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = (int)  $this->option('chunk');

        if ($dryRun) {
            $this->components->warn('DRY RUN — no changes will be made.');
        }

        $this->newLine();

        $pruning = config('decodo.pruning', []);

        $contentDays      = (int) ($pruning['content_days']       ?? 7);
        $tasksDays        = (int) ($pruning['tasks_days']         ?? 30);
        $pendingTasksDays = (int) ($pruning['pending_tasks_days'] ?? 3);
        $batchesDays      = (int) ($pruning['batches_days']       ?? 60);

        $this->printConfig($contentDays, $tasksDays, $pendingTasksDays, $batchesDays);

        $nullified = $this->nullifyContent($contentDays, $chunk, $dryRun);
        $tasks     = $this->pruneTasks($tasksDays, $pendingTasksDays, $chunk, $dryRun);
        $batches   = $this->pruneBatches($batchesDays, $chunk, $dryRun);

        $this->newLine();
        $this->printSummary($nullified, $tasks, $batches, $dryRun);

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Stage 1 — Null result_content to reclaim column storage
    // -------------------------------------------------------------------------

    private function nullifyContent(int $days, int $chunk, bool $dryRun): int
    {
        $cutoff = now()->subDays($days);

        $count = DecodoTask::whereNotNull('result_content')
            ->where('completed_at', '<', $cutoff)
            ->count();

        $this->components->twoColumnDetail(
            "Nullify <comment>result_content</comment> (completed > {$days}d ago)",
            "<info>{$count} row(s)</info>",
        );

        if ($count === 0 || $dryRun) {
            return $count;
        }

        $nullified = 0;
        do {
            $affected = DecodoTask::whereNotNull('result_content')
                ->where('completed_at', '<', $cutoff)
                ->limit($chunk)
                ->update(['result_content' => null]);
            $nullified += $affected;
        } while ($affected === $chunk);

        return $nullified;
    }

    // -------------------------------------------------------------------------
    // Stage 2 — Delete terminal + expired-pending task rows
    // -------------------------------------------------------------------------

    private function pruneTasks(int $days, int $pendingDays, int $chunk, bool $dryRun): int
    {
        // Terminal tasks (done / faulted).
        $terminalCutoff = now()->subDays($days);
        $terminalCount  = DecodoTask::whereIn('status', ['done', 'faulted'])
            ->where('completed_at', '<', $terminalCutoff)
            ->count();

        $this->components->twoColumnDetail(
            "Delete <comment>done/faulted</comment> tasks (completed > {$days}d ago)",
            "<info>{$terminalCount} row(s)</info>",
        );

        // Stuck-pending tasks.
        $pendingCutoff = now()->subDays($pendingDays);
        $pendingCount  = DecodoTask::where('status', 'pending')
            ->where('queued_at', '<', $pendingCutoff)
            ->count();

        $this->components->twoColumnDetail(
            "Delete <comment>stuck-pending</comment> tasks (queued > {$pendingDays}d ago)",
            "<info>{$pendingCount} row(s)</info>",
        );

        if (($terminalCount + $pendingCount) === 0 || $dryRun) {
            return $terminalCount + $pendingCount;
        }

        $deleted = 0;

        // Delete terminal tasks in chunks.
        do {
            $affected = DecodoTask::whereIn('status', ['done', 'faulted'])
                ->where('completed_at', '<', $terminalCutoff)
                ->limit($chunk)
                ->delete();
            $deleted += $affected;
        } while ($affected === $chunk);

        // Fire DecodoTaskExpired before deleting each stuck-pending task so
        // listeners can alert, log, or trigger a retry.
        DecodoTask::where('status', 'pending')
            ->where('queued_at', '<', $pendingCutoff)
            ->chunkById($chunk, function ($expiredTasks) use (&$deleted) {
                foreach ($expiredTasks as $task) {
                    DecodoTaskExpired::dispatch($task, 'pending_timeout');
                    $task->delete();
                    $deleted++;
                }
            });

        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Stage 3 — Delete terminal batch rows
    // -------------------------------------------------------------------------

    private function pruneBatches(int $days, int $chunk, bool $dryRun): int
    {
        $cutoff = now()->subDays($days);

        $count = DecodoBatch::whereIn('status', ['done', 'faulted', 'partial'])
            ->where('completed_at', '<', $cutoff)
            ->count();

        $this->components->twoColumnDetail(
            "Delete <comment>terminal batches</comment> (completed > {$days}d ago)",
            "<info>{$count} row(s)</info>",
        );

        if ($count === 0 || $dryRun) {
            return $count;
        }

        $deleted = 0;
        do {
            $affected = DecodoBatch::whereIn('status', ['done', 'faulted', 'partial'])
                ->where('completed_at', '<', $cutoff)
                ->limit($chunk)
                ->delete();
            $deleted += $affected;
        } while ($affected === $chunk);

        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Output helpers
    // -------------------------------------------------------------------------

    private function printConfig(int $c, int $t, int $p, int $b): void
    {
        $this->components->info('Retention windows:');
        $this->components->twoColumnDetail('Null result_content after',      "{$c} days");
        $this->components->twoColumnDetail('Delete done/faulted tasks after', "{$t} days");
        $this->components->twoColumnDetail('Delete stuck-pending tasks after', "{$p} days");
        $this->components->twoColumnDetail('Delete terminal batches after',   "{$b} days");
        $this->newLine();
    }

    private function printSummary(int $nullified, int $tasks, int $batches, bool $dryRun): void
    {
        $label = $dryRun ? 'Would affect' : 'Pruned';
        $this->components->info("{$label}:");
        $this->components->twoColumnDetail('result_content nullified', (string) $nullified);
        $this->components->twoColumnDetail('Task rows deleted',        (string) $tasks);
        $this->components->twoColumnDetail('Batch rows deleted',       (string) $batches);
    }
}
