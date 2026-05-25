<?php

namespace Rkdhatterwal\DecodoScraper;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Rkdhatterwal\DecodoScraper\DTOs\BatchTaskResponse;
use Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult;
use Rkdhatterwal\DecodoScraper\DTOs\TaskResponse;
use Rkdhatterwal\DecodoScraper\Exceptions\DecodoException;
use Rkdhatterwal\DecodoScraper\Models\DecodoBatch;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Decodo Async Client
 *
 * Wraps the v3 asynchronous endpoints:
 *   POST   /task              — queue a single task
 *   GET    /task/{id}         — check task status
 *   GET    /task/{id}/results — retrieve task results
 *   POST   /task/batch        — queue multiple tasks
 *
 * Features:
 *   - Optional DB persistence (decodo_tasks / decodo_batches)
 *   - Automatic callback URL injection from the registered webhook route
 *   - Batch rate-limit guard (Decodo enforces 1 req/sec on /task/batch)
 *
 * Docs: https://help.decodo.com/docs/web-scraping-api-asynchronous-requests
 */
class AsyncDecodoClient
{
    private string $baseUrl;

    private int $timeout;

    private array $defaults;

    private bool $dbEnabled;

    private bool $autoCallbackUrl;

    private int $batchRateLimitMs;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $token,
        array $config = [],
    ) {
        if (empty($this->token)) {
            throw DecodoException::missingToken();
        }

        $this->baseUrl = rtrim($config['async_base_url'] ?? 'https://scraper-api.decodo.com/v3', '/');
        $this->timeout = $config['timeout'] ?? 30;
        $this->defaults = $config['defaults'] ?? [];
        $this->dbEnabled = $config['database']['enabled'] ?? true;
        $this->autoCallbackUrl = $config['webhook']['auto_inject_callback'] ?? true;
        $this->batchRateLimitMs = $config['batch_rate_limit_ms'] ?? 1_000;
    }

    // -------------------------------------------------------------------------
    // Queue tasks
    // -------------------------------------------------------------------------

    /**
     * Queue a single async scrape task.
     *
     * When `webhook.auto_inject_callback` is true (default) and no callbackUrl
     * is provided, the package webhook route is used automatically.
     *
     * @param  array<string, mixed>  $options
     * @param  string|null  $callbackUrl  Override the auto-injected URL.
     * @param  string|null  $passthrough  Echoed back in callback for verification.
     * @param  Model|null  $scrapeable  App model to associate.
     */
    public function queueTask(
        string $url,
        array $options = [],
        ?string $callbackUrl = null,
        ?string $passthrough = null,
        ?Model $scrapeable = null,
    ): TaskResponse {
        $resolvedCallback = $callbackUrl ?? $this->resolveCallbackUrl('task');

        $builder = PayloadBuilder::fromDefaults($this->defaults, $options)->url($url);

        if ($resolvedCallback !== null) {
            $builder->callbackUrl($resolvedCallback);
        }

        if ($passthrough !== null) {
            $builder->passthrough($passthrough);
        }

        $payload = $builder->build();
        $response = $this->post('/task', $payload);
        $dto = TaskResponse::fromArray($response);

        $this->persistTask($dto, $payload, $options, $resolvedCallback, $passthrough, $scrapeable);

        return $dto;
    }

    /**
     * Queue a single task using a PayloadBuilder for full control.
     * Auto callback injection is skipped — the builder's value takes precedence.
     */
    public function queueTaskWithBuilder(
        PayloadBuilder $builder,
        ?Model $scrapeable = null,
    ): TaskResponse {
        $payload = $builder->build();
        $response = $this->post('/task', $payload);
        $dto = TaskResponse::fromArray($response);

        $this->persistTask($dto, $payload, [], $dto->callbackUrl, $dto->passthrough, $scrapeable);

        return $dto;
    }

    /**
     * Queue a batch of URLs as a single batch request.
     *
     * Enforces Decodo's 1-request-per-second rate limit automatically.
     * When `webhook.auto_inject_callback` is true and no callbackUrl is given,
     * the package batch webhook route is injected automatically.
     *
     * @param  string[]  $urls
     * @param  array<string, mixed>  $options
     * @param  string|null  $callbackUrl  Fired once the whole batch completes.
     * @param  string|null  $batchName  Optional label for the local batch record.
     */
    public function queueBatch(
        array $urls,
        array $options = [],
        ?string $callbackUrl = null,
        ?string $batchName = null,
    ): BatchTaskResponse {
        $resolvedCallback = $callbackUrl ?? $this->resolveCallbackUrl('batch');

        // Enforce Decodo's 1 req/sec rate limit for batch submissions.
        BatchRateLimiter::throttle($this->batchRateLimitMs);

        $builder = PayloadBuilder::fromDefaults($this->defaults, $options);

        if ($resolvedCallback !== null) {
            $builder->callbackUrl($resolvedCallback);
        }

        $payload = $builder->buildBatch($urls);
        $response = $this->post('/task/batch', $payload);

        $dto = BatchTaskResponse::fromArray($response);

        $this->persistBatch($dto, $urls, $options, $resolvedCallback, $batchName);

        return $dto;
    }

    // -------------------------------------------------------------------------
    // Status & result retrieval
    // -------------------------------------------------------------------------

    /**
     * Check the current status of a queued task.
     * Status values: "pending" | "done" | "faulted"
     */
    public function getTaskStatus(string $taskId): TaskResponse
    {
        $this->assertTaskId($taskId);

        return TaskResponse::fromArray($this->get("/task/{$taskId}"));
    }

    /**
     * Retrieve scrape results for a completed task.
     * Results can be fetched unlimited times within 24 hours.
     *
     * @return Collection<int, ScrapeResult>
     */
    public function getTaskResults(string $taskId): Collection
    {
        $this->assertTaskId($taskId);

        $response = $this->get("/task/{$taskId}/results");

        if (empty($response['results'])) {
            throw DecodoException::emptyResponse();
        }

        return collect($response['results'])->map(
            fn (array $result) => ScrapeResult::fromArray($result)
        );
    }

    /**
     * Convenience: retrieve the first result from a completed task.
     */
    public function getFirstTaskResult(string $taskId): ScrapeResult
    {
        return $this->getTaskResults($taskId)->first()
            ?? throw DecodoException::emptyResponse();
    }

    /**
     * Poll a task until "done" or "faulted", then return results.
     *
     * Prefer callback webhooks in production; polling is for scripts/CLI.
     *
     * @param  int  $intervalMs  Milliseconds between polls (default 2000).
     * @param  int  $maxAttempts  Max polling attempts (default 30).
     * @return Collection<int, ScrapeResult>
     */
    public function pollUntilDone(
        string $taskId,
        int $intervalMs = 2_000,
        int $maxAttempts = 30,
    ): Collection {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $status = $this->getTaskStatus($taskId);

            if ($status->isDone()) {
                return $this->getTaskResults($taskId);
            }

            if ($status->isFaulted()) {
                throw new DecodoException("Task [{$taskId}] faulted.");
            }

            if ($attempt < $maxAttempts) {
                usleep($intervalMs * 1_000);
            }
        }

        throw new DecodoException(
            "Task [{$taskId}] did not complete after {$maxAttempts} attempts."
        );
    }

    // -------------------------------------------------------------------------
    // Auto callback URL injection (Feature #5)
    // -------------------------------------------------------------------------

    /**
     * Resolve the webhook URL to inject into outgoing requests.
     *
     * Returns null when:
     *   - auto_inject_callback is disabled in config, or
     *   - webhook.enabled is false, or
     *   - the named route is not registered (e.g. routes not yet loaded).
     *
     * @param  'task'|'batch'  $type
     */
    private function resolveCallbackUrl(string $type): ?string
    {
        if (! $this->autoCallbackUrl) {
            return null;
        }

        if (! config('decodo.webhook.enabled', true)) {
            return null;
        }

        $routeName = "decodo.webhook.{$type}";

        try {
            return route($routeName);
        } catch (\Throwable) {
            // Route not registered — silently skip injection.
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // DB persistence helpers
    // -------------------------------------------------------------------------

    private function persistTask(
        TaskResponse $dto,
        array $payload,
        array $options,
        ?string $callbackUrl,
        ?string $passthrough,
        ?Model $scrapeable,
        ?int $batchId = null,
    ): void {
        if (! $this->shouldPersist()) {
            return;
        }

        $data = [
            'decodo_task_id' => $dto->id,
            'decodo_batch_id' => $batchId,
            'url' => $dto->url ?: ($payload['url'] ?? null),
            'query' => $payload['query'] ?? null,
            'status' => 'pending',
            'payload' => $payload,
            'options' => $options,
            'callback_url' => $callbackUrl,
            'passthrough' => $passthrough,
            'queued_at' => now(),
        ];

        if ($scrapeable) {
            $data['scrapeable_type'] = $scrapeable->getMorphClass();
            $data['scrapeable_id'] = $scrapeable->getKey();
        }

        DecodoTask::create($data);
    }

    private function persistBatch(
        BatchTaskResponse $dto,
        array $urls,
        array $options,
        ?string $callbackUrl,
        ?string $batchName,
    ): void {
        if (! $this->shouldPersist()) {
            return;
        }

        $batch = DecodoBatch::create([
            'name' => $batchName,
            'total_tasks' => count($urls),
            'status' => 'pending',
            'callback_url' => $callbackUrl,
            'options' => $options,
            'started_at' => now(),
        ]);

        $dto->tasks->each(function (
            TaskResponse $task,
            int $index
        ) use ($batch, $urls, $options, $callbackUrl) {
            $this->persistTask(
                $task,
                ['url' => $urls[$index] ?? ''],
                $options,
                $callbackUrl,
                null,
                null,
                $batch->id,
            );
        });
    }

    private function shouldPersist(): bool
    {
        if (! $this->dbEnabled) {
            return false;
        }

        try {
            return Schema::hasTable('decodo_tasks');
        } catch (\Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    private function post(string $path, array $payload): array
    {
        $response = $this->http
            ->withToken($this->token, 'Basic')
            ->timeout($this->timeout)
            ->acceptJson()
            ->post($this->baseUrl.$path, $payload);

        if ($response->failed()) {
            throw DecodoException::requestFailed($response->status(), $response->body());
        }

        return $response->json() ?? [];
    }

    private function get(string $path): array
    {
        $response = $this->http
            ->withToken($this->token, 'Basic')
            ->timeout($this->timeout)
            ->acceptJson()
            ->get($this->baseUrl.$path);

        if ($response->failed()) {
            throw DecodoException::requestFailed($response->status(), $response->body());
        }

        return $response->json() ?? [];
    }

    private function assertTaskId(string $taskId): void
    {
        if (empty(trim($taskId))) {
            throw new \InvalidArgumentException('Task ID cannot be empty.');
        }
    }
}
