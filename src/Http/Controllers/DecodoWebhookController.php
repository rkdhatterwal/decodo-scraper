<?php

namespace Rkdhatterwal\DecodoScraper\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Rkdhatterwal\DecodoScraper\DecodoLogger;
use Rkdhatterwal\DecodoScraper\Events\DecodoBatchCompleted;
use Rkdhatterwal\DecodoScraper\Events\DecodoTaskCompleted;
use Rkdhatterwal\DecodoScraper\Events\DecodoTaskFaulted;
use Rkdhatterwal\DecodoScraper\Models\DecodoBatch;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Handles POST callbacks from Decodo's async scraping API.
 *
 * Decodo sends a POST to your callback_url once a task completes.
 * The body contains the task ID and status; use the task ID to fetch results.
 *
 * Two routes are registered (see routes/decodo.php):
 *   POST /decodo/webhook/task    — single task callback
 *   POST /decodo/webhook/batch   — batch completion callback
 */
class DecodoWebhookController extends Controller
{
    // -------------------------------------------------------------------------
    // Single task webhook
    // -------------------------------------------------------------------------

    /**
     * Handle a single-task completion callback.
     *
     * Decodo POSTs:
     * {
     *   "id": "7039164056019693569",
     *   "status": "done",
     *   "url": "https://example.com",
     *   "passthrough": "your-secret",
     *   ...
     * }
     */
    public function handleTask(Request $request): JsonResponse
    {
        $payload = $request->all();

        $decodoTaskId = $payload['id'] ?? null;
        $status       = $payload['status'] ?? null;

        if (! $decodoTaskId || ! $status) {
            DecodoLogger::warning('DecodoWebhook: received malformed task payload.', $payload);
            return response()->json(['message' => 'Missing id or status.'], 422);
        }

        // Find the locally-tracked task record.
        $task = DecodoTask::where('decodo_task_id', $decodoTaskId)->first();

        if (! $task) {
            // We may receive webhooks for tasks queued without DB persistence.
            // Log and acknowledge so Decodo doesn't retry.
            DecodoLogger::info("DecodoWebhook: task [{$decodoTaskId}] not found in local DB — acknowledged.");
            return response()->json(['message' => 'Task not tracked locally.']);
        }

        match ($status) {
            'done'    => $this->handleTaskDone($task, $decodoTaskId, $payload),
            'faulted' => $this->handleTaskFaulted($task, $payload),
            default   => DecodoLogger::info("DecodoWebhook: task [{$decodoTaskId}] has unhandled status [{$status}]."),
        };

        return response()->json(['message' => 'OK']);
    }

    // -------------------------------------------------------------------------
    // Batch webhook
    // -------------------------------------------------------------------------

    /**
     * Handle a batch completion callback.
     *
     * Decodo fires this after the last task in the batch finishes.
     * We recalculate the aggregate batch status and fire DecodoBatchCompleted.
     *
     * Note: individual task webhooks arrive separately via handleTask().
     * This endpoint is for the batch-level notification.
     */
    public function handleBatch(Request $request): JsonResponse
    {
        $payload = $request->all();

        $passthrough = $payload['passthrough'] ?? null;

        // Locate the batch by its passthrough token (the most reliable
        // correlation key since batches don't have a single Decodo task_id).
        $batch = $passthrough
            ? DecodoBatch::where('passthrough', $passthrough)->first()
            : null;

        if (! $batch) {
            DecodoLogger::info('DecodoWebhook: batch not found by passthrough — acknowledged.', $payload);
            return response()->json(['message' => 'Batch not tracked locally.']);
        }

        // Recompute aggregate status from child tasks.
        $batch->recalculateStatus();
        $batch->refresh();

        // Only fire the event when the batch has truly finished (not still pending).
        if (! $batch->isPending()) {
            DecodoBatchCompleted::dispatch($batch);
        }

        return response()->json(['message' => 'OK']);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function handleTaskDone(DecodoTask $task, string $decodoTaskId, array $payload): void
    {
        // Fetch the full scrape result from Decodo.
        $result = $this->fetchTaskResult($decodoTaskId);

        $content    = $result['content']     ?? '';
        $statusCode = $result['status_code'] ?? 0;

        $task->markDone($content, $statusCode, $payload);

        DecodoTaskCompleted::dispatch($task, $payload);

        // If this task belongs to a batch, check whether the batch is now complete.
        if ($task->decodo_batch_id) {
            $batch = $task->batch;
            $batch?->recalculateStatus();
            $batch?->refresh();

            if ($batch && ! $batch->isPending()) {
                DecodoBatchCompleted::dispatch($batch);
            }
        }
    }

    private function handleTaskFaulted(DecodoTask $task, array $payload): void
    {
        $task->markFaulted($payload);

        DecodoTaskFaulted::dispatch($task, $payload);

        // Same batch-completion check on fault.
        if ($task->decodo_batch_id) {
            $batch = $task->batch;
            $batch?->recalculateStatus();
            $batch?->refresh();

            if ($batch && ! $batch->isPending()) {
                DecodoBatchCompleted::dispatch($batch);
            }
        }
    }

    /**
     * Pull the first result item for a completed task from the Decodo API.
     */
    private function fetchTaskResult(string $decodoTaskId): array
    {
        try {
            /** @var \Rkdhatterwal\DecodoScraper\AsyncDecodoClient $client */
            $client  = app('decodo.async');
            $results = $client->getTaskResults($decodoTaskId);

            return $results->first()?->toArray() ?? [];
        } catch (\Throwable $e) {
            DecodoLogger::error("DecodoWebhook: failed to fetch result for task [{$decodoTaskId}]: {$e->getMessage()}");
            return [];
        }
    }
}
