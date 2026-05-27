<?php

namespace Rkdhatterwal\DecodoScraper\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Rkdhatterwal\DecodoScraper\AsyncDecodoClient;
use Rkdhatterwal\DecodoScraper\DecodoLogger;
use Rkdhatterwal\DecodoScraper\Events\DecodoBatchCompleted;
use Rkdhatterwal\DecodoScraper\Events\DecodoTaskCompleted;
use Rkdhatterwal\DecodoScraper\Events\DecodoTaskFaulted;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Handles POST callbacks from Decodo's async scraping API.
 *
 * Decodo sends a POST to your callback_url once a task completes.
 * The body contains the task ID and status; use the task ID to fetch results.
 *
 * Two routes are registered (see routes/decodo.php):
 *   POST /decodo/webhook/task    — task callback
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
     *   "task_id": "7465383408571557890",
     *   "status_code": 200,
     *   "passthrough": "your-secret",
     *   ...
     * }
     */
    public function handleTask(Request $request): JsonResponse
    {
        $payload = $request->all();

        $decodoTaskId = $payload['task_id'] ?? null;
        $status       = $payload['status_code'] ?? null;
        $passthrough  = $payload['passthrough'] ?? null;

        if (! $decodoTaskId || is_null($status)) {
            DecodoLogger::warning('DecodoWebhook: received malformed task payload.', $payload);
            return response()->json(['message' => 'Missing id or status.'], 422);
        }

        // If status is a numeric status code (new format), map it to internal status strings.
        if (is_numeric($status)) {
            $status = ($status >= 200 && $status < 300) ? 'done' : 'faulted';
        }

        // Find the locally tracked task record.
        $task = DecodoTask::where('decodo_task_id', $decodoTaskId)->first();

        if (! $task) {
            // We may receive webhooks for tasks queued without DB persistence.
            // Log and acknowledge so Decodo doesn't retry.
            DecodoLogger::info("DecodoWebhook: task [{$decodoTaskId}] not found in local DB — acknowledged.");
            return response()->json(['message' => 'Task not tracked locally.']);
        }

        // Validate passthrough for the specific task/batch if available.
        if ($task->passthrough && ! hash_equals((string) $task->passthrough, (string) $passthrough)) {
            DecodoLogger::warning("DecodoWebhook: task [{$decodoTaskId}] passthrough mismatch.");
            return response()->json(['message' => 'Invalid passthrough.'], 403);
        }

        match ($status) {
            'done'    => $this->handleTaskDone($task, $decodoTaskId, $payload),
            'faulted' => $this->handleTaskFaulted($task, $payload),
            default   => DecodoLogger::info("DecodoWebhook: task [{$decodoTaskId}] has unhandled status [{$status}]."),
        };

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

        // If this task belongs to a batch, recalculate the batch status.
        $this->updateBatchStatus($task);
    }

    private function handleTaskFaulted(DecodoTask $task, array $payload): void
    {
        $task->markFaulted($payload);

        DecodoTaskFaulted::dispatch($task, $payload);

        // If this task belongs to a batch, recalculate the batch status.
        $this->updateBatchStatus($task);
    }

    /**
     * Pull the first result item for a completed task from the Decodo API.
     */
    private function fetchTaskResult(string $decodoTaskId): array
    {
        try {
            /** @var AsyncDecodoClient $client */
            $client  = app('decodo.async');
            $results = $client->getTaskResults($decodoTaskId);

            return $results->first()?->toArray() ?? [];
        } catch (\Throwable $e) {
            DecodoLogger::error("DecodoWebhook: failed to fetch result for task [{$decodoTaskId}]: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * @param  DecodoTask  $task
     * @return void
     */
    private function updateBatchStatus(DecodoTask $task): void
    {
        if ($task->decodo_batch_id) {
            $batch = $task->batch;

            if ($batch && $batch->isPending()) {
                $batch->recalculateStatus();
                $batch->refresh();

                // Only fire the event when the batch has truly finished.
                if (!$batch->isPending()) {
                    DecodoBatchCompleted::dispatch($batch);
                }
            }
        }
    }
}
