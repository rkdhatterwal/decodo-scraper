<?php

namespace Rkdhatterwal\DecodoScraper\Console\Commands;

use Illuminate\Console\Command;
use Rkdhatterwal\DecodoScraper\AsyncDecodoClient;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Re-queue faulted (or stuck-pending) Decodo tasks.
 *
 * Usage:
 *   php artisan decodo:retry                       # retry all faulted tasks
 *   php artisan decodo:retry --status=pending      # retry stuck-pending tasks
 *   php artisan decodo:retry --status=faulted,pending
 *   php artisan decodo:retry --limit=25            # cap how many are re-queued
 *   php artisan decodo:retry --id=7434928397127555073  # retry one specific task
 *   php artisan decodo:retry --dry-run             # preview without re-queuing
 */
class RetryDecodoTasks extends Command
{
    protected $signature = 'decodo:retry
                            {--status=faulted        : Comma-separated statuses to retry (faulted, pending)}
                            {--id=                   : Retry a single task by its Decodo task ID}
                            {--limit=100             : Maximum number of tasks to re-queue in one run}
                            {--dry-run               : Preview which tasks would be retried without acting}';

    protected $description = 'Re-queue faulted or stuck-pending Decodo tasks via the async API';

    public function __construct(private readonly AsyncDecodoClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('DRY RUN — no tasks will be re-queued.');
        }

        $tasks = $this->resolveTasks();

        if ($tasks->isEmpty()) {
            $this->components->info('No tasks matched the given criteria.');
            return self::SUCCESS;
        }

        $this->components->info("Found {$tasks->count()} task(s) to retry.");
        $this->newLine();

        $retried = 0;
        $failed  = 0;

        foreach ($tasks as $task) {
            $label = $task->decodo_task_id . ' (' . ($task->url ?? $task->query ?? 'no url') . ')';

            if ($dryRun) {
                $this->components->twoColumnDetail($label, '<comment>would retry</comment>');
                $retried++;
                continue;
            }

            try {
                $response = $this->client->queueTask(
                    url:         $task->url ?? '',
                    options:     $task->options ?? [],
                    callbackUrl: $task->callback_url,
                    passthrough: $task->passthrough,
                    scrapeable:  $task->scrapeable,
                );

                // Update the existing row to point to the new Decodo task ID.
                $task->update([
                    'decodo_task_id' => $response->id,
                    'status'         => 'pending',
                    'result_content' => null,
                    'result_status_code' => null,
                    'webhook_payload'    => null,
                    'completed_at'       => null,
                    'queued_at'          => now(),
                ]);

                $this->components->twoColumnDetail($label, "<info>re-queued as {$response->id}</info>");
                $retried++;
            } catch (\Throwable $e) {
                $this->components->twoColumnDetail($label, "<error>failed: {$e->getMessage()}</error>");
                $failed++;
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'Would retry' : 'Retried';
        $this->components->info("{$verb}: {$retried} | Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveTasks()
    {
        $limit = max(1, (int) $this->option('limit'));

        // Single-task mode.
        if ($id = $this->option('id')) {
            return DecodoTask::where('decodo_task_id', $id)->limit(1)->get();
        }

        // Batch mode — resolve statuses.
        $statuses = collect(explode(',', $this->option('status')))
            ->map(fn ($s) => trim(strtolower($s)))
            ->filter(fn ($s) => in_array($s, ['faulted', 'pending'], true))
            ->values()
            ->all();

        if (empty($statuses)) {
            $this->components->error('Invalid --status value. Allowed: faulted, pending');
            return collect();
        }

        return DecodoTask::whereIn('status', $statuses)
            ->whereNotNull('url')   // can only retry tasks with a known URL
            ->orderBy('queued_at')  // oldest first
            ->limit($limit)
            ->get();
    }
}
