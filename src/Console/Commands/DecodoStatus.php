<?php

namespace Rkdhatterwal\DecodoScraper\Console\Commands;

use Illuminate\Console\Command;
use Rkdhatterwal\DecodoScraper\AsyncDecodoClient;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Inspect a Decodo task by its ID — checks both the local DB and the live API.
 *
 * Usage:
 *   php artisan decodo:status 7434928397127555073
 *   php artisan decodo:status 7434928397127555073 --preview=500
 *   php artisan decodo:status 7434928397127555073 --api-only
 */
class DecodoStatus extends Command
{
    protected $signature = 'decodo:status
                            {id                 : Decodo task ID to inspect}
                            {--preview=300      : Number of characters of result_content to preview (0 = none)}
                            {--api-only         : Skip local DB lookup and query Decodo API directly}';

    protected $description = 'Inspect the status and details of a Decodo async task';

    public function handle(AsyncDecodoClient $client): int
    {
        $taskId  = $this->argument('id');
        $apiOnly = (bool) $this->option('api-only');
        $preview = (int)  $this->option('preview');

        $this->newLine();
        $this->components->info("Decodo Task: {$taskId}");
        $this->newLine();

        // ---- Local DB record ------------------------------------------------
        if (! $apiOnly) {
            $this->showLocalRecord($taskId, $preview);
        }

        // ---- Live API status ------------------------------------------------
        $this->showApiStatus($client, $taskId);

        return self::SUCCESS;
    }

    private function showLocalRecord(string $taskId, int $preview): void
    {
        $task = DecodoTask::where('decodo_task_id', $taskId)->first();

        $this->components->info('─── Local DB Record ───');

        if (! $task) {
            $this->components->warn('Not found in local database.');
            $this->newLine();
            return;
        }

        $statusColor = match ($task->status) {
            'done'    => 'info',
            'faulted' => 'error',
            default   => 'comment',
        };

        $this->components->twoColumnDetail('Local ID',         (string) $task->id);
        $this->components->twoColumnDetail('Status',           "<{$statusColor}>{$task->status}</{$statusColor}>");
        $this->components->twoColumnDetail('URL',              $task->url ?? $task->query ?? '—');
        $this->components->twoColumnDetail('Batch ID',         $task->decodo_batch_id ? (string) $task->decodo_batch_id : '—');
        $this->components->twoColumnDetail('Callback URL',     $task->callback_url ?? '—');
        $this->components->twoColumnDetail('Queued At',        $task->queued_at?->toDateTimeString() ?? '—');
        $this->components->twoColumnDetail('Completed At',     $task->completed_at?->toDateTimeString() ?? '—');
        $this->components->twoColumnDetail('HTTP Status Code', $task->result_status_code ? (string) $task->result_status_code : '—');

        if ($preview > 0 && $task->result_content) {
            $this->newLine();
            $this->components->info('─── Content Preview ───');
            $this->line(mb_substr(strip_tags($task->result_content), 0, $preview) . '…');
        } elseif ($preview > 0) {
            $this->components->twoColumnDetail('Content', '— (not stored)');
        }

        $this->newLine();
    }

    private function showApiStatus(AsyncDecodoClient $client, string $taskId): void
    {
        $this->components->info('─── Live API Status ───');

        try {
            $status      = $client->getTaskStatus($taskId);
            $statusColor = match ($status->status) {
                'done'    => 'info',
                'faulted' => 'error',
                default   => 'comment',
            };

            $this->components->twoColumnDetail('Status',      "<{$statusColor}>{$status->status}</{$statusColor}>");
            $this->components->twoColumnDetail('URL',         $status->url ?: ($status->query ?? '—'));
            $this->components->twoColumnDetail('Target',      $status->target ?? '—');
            $this->components->twoColumnDetail('Device',      $status->deviceType);
            $this->components->twoColumnDetail('Geo',         $status->geo ?? '—');
            $this->components->twoColumnDetail('Headless',    $status->headless ?? 'off');
            $this->components->twoColumnDetail('Parse',       $status->parse ? 'yes' : 'no');
            $this->components->twoColumnDetail('Created At',  $status->createdAt);
            $this->components->twoColumnDetail('Updated At',  $status->updatedAt);
        } catch (\Throwable $e) {
            $this->components->error("API error: {$e->getMessage()}");
        }

        $this->newLine();
    }
}
