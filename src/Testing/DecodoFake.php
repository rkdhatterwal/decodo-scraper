<?php

namespace Rkdhatterwal\DecodoScraper\Testing;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Rkdhatterwal\DecodoScraper\AsyncDecodoClient;
use Rkdhatterwal\DecodoScraper\DecodoClient;
use Rkdhatterwal\DecodoScraper\DTOs\BatchTaskResponse;
use Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult;
use Rkdhatterwal\DecodoScraper\DTOs\TaskResponse;
use Rkdhatterwal\DecodoScraper\Exceptions\DecodoException;

/**
 * DecodoFake — a drop-in test double for both DecodoClient and AsyncDecodoClient.
 *
 * Inspired by Laravel's Http::fake(), Queue::fake(), etc.
 *
 * Usage in a test:
 *
 *   $fake = DecodoFake::make();
 *
 *   // Stub responses before acting
 *   $fake->fakeScrape('<html>Hello</html>', 200);
 *   $fake->fakeTask('task-001');
 *   $fake->fakeBatch(['task-001', 'task-002']);
 *   $fake->fakeTaskStatus('task-001', 'done');
 *   $fake->fakeTaskResults('task-001', '<html>Result</html>');
 *
 *   // Bind into the container
 *   $fake->swap();
 *
 *   // ... act ...
 *
 *   // Assert
 *   $fake->assertScraped('https://example.com');
 *   $fake->assertTaskQueued('https://example.com');
 *   $fake->assertBatchQueued(2);
 *   $fake->assertScrapeCount(1);
 *
 *   // Or use the static shorthand
 *   [$sync, $async] = DecodoFake::make()->swap();
 */
class DecodoFake
{
    // ---- Recorded calls ----------------------------------------------------
    private array $scrapes         = [];
    private array $queuedTasks     = [];
    private array $queuedBatches   = [];
    private array $statusChecks    = [];
    private array $resultFetches   = [];

    // ---- Stubbed responses -------------------------------------------------
    private array $scrapeResponses       = [];
    private array $taskResponses         = [];
    private array $batchResponses        = [];
    private array $statusResponses       = [];
    private array $resultResponses       = [];

    private bool $failOnUnstubbed = false;

    // =========================================================================
    // Factory
    // =========================================================================

    public static function make(): static
    {
        return new static();
    }

    /**
     * Bind this fake as both the sync and async client in the service container.
     *
     * @return $this
     */
    public function swap(): static
    {
        app()->instance(DecodoClient::class,      $this->asSyncClient());
        app()->instance(AsyncDecodoClient::class,  $this->asAsyncClient());
        app()->instance('decodo',       $this->asSyncClient());
        app()->instance('decodo.async', $this->asAsyncClient());

        return $this;
    }

    // =========================================================================
    // Stubbing helpers
    // =========================================================================

    /**
     * Stub a real-time scrape response.
     *
     * @param  string|array  $content  HTML string or array of ScrapeResult arrays.
     */
    public function fakeScrape(string|array $content = '<html></html>', int $statusCode = 200): static
    {
        $this->scrapeResponses[] = is_array($content)
            ? $content
            : $this->makeResult($content, $statusCode);

        return $this;
    }

    /**
     * Stub a task queue response.
     */
    public function fakeTask(string $taskId = 'fake-task-001', string $status = 'pending', string $url = 'https://example.com'): static
    {
        $this->taskResponses[] = $this->makeTaskData($taskId, $status, $url);
        return $this;
    }

    /**
     * Stub a batch queue response — one task ID per URL.
     *
     * @param  string[]  $taskIds
     */
    public function fakeBatch(array $taskIds = ['fake-task-001']): static
    {
        $this->batchResponses[] = $taskIds;
        return $this;
    }

    /**
     * Stub a task status check response.
     */
    public function fakeTaskStatus(string $taskId, string $status = 'done'): static
    {
        $this->statusResponses[$taskId] = $this->makeTaskData($taskId, $status);
        return $this;
    }

    /**
     * Stub results for a specific task ID.
     */
    public function fakeTaskResults(string $taskId, string $content = '<html></html>', int $statusCode = 200): static
    {
        $this->resultResponses[$taskId] = collect([$this->makeResult($content, $statusCode, $taskId)]);
        return $this;
    }

    /**
     * Throw DecodoException for any unstubbed call instead of returning empty defaults.
     */
    public function failOnUnstubbed(bool $fail = true): static
    {
        $this->failOnUnstubbed = $fail;
        return $this;
    }

    // =========================================================================
    // Assertions
    // =========================================================================

    public function assertScraped(string $url): void
    {
        Assert::assertTrue(
            collect($this->scrapes)->contains(fn ($s) => $s['url'] === $url),
            "Expected URL [{$url}] to have been scraped, but it was not."
        );
    }

    public function assertNotScraped(string $url): void
    {
        Assert::assertFalse(
            collect($this->scrapes)->contains(fn ($s) => $s['url'] === $url),
            "Expected URL [{$url}] NOT to have been scraped, but it was."
        );
    }

    public function assertScrapeCount(int $count): void
    {
        Assert::assertCount($count, $this->scrapes, "Expected {$count} scrape(s), got " . count($this->scrapes) . '.');
    }

    public function assertTaskQueued(string $url): void
    {
        Assert::assertTrue(
            collect($this->queuedTasks)->contains(fn ($t) => $t['url'] === $url),
            "Expected task for [{$url}] to have been queued, but it was not."
        );
    }

    public function assertTaskNotQueued(string $url): void
    {
        Assert::assertFalse(
            collect($this->queuedTasks)->contains(fn ($t) => $t['url'] === $url),
            "Expected task for [{$url}] NOT to have been queued, but it was."
        );
    }

    public function assertTaskQueuedCount(int $count): void
    {
        Assert::assertCount($count, $this->queuedTasks, "Expected {$count} task(s) queued, got " . count($this->queuedTasks) . '.');
    }

    public function assertBatchQueued(int $urlCount): void
    {
        Assert::assertTrue(
            collect($this->queuedBatches)->contains(fn ($b) => count($b['urls']) === $urlCount),
            "Expected a batch with {$urlCount} URL(s) to have been queued."
        );
    }

    public function assertBatchQueuedCount(int $count): void
    {
        Assert::assertCount($count, $this->queuedBatches, "Expected {$count} batch(es) queued, got " . count($this->queuedBatches) . '.');
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->scrapes,       'Expected no scrapes, but some were sent.');
        Assert::assertEmpty($this->queuedTasks,   'Expected no tasks queued, but some were.');
        Assert::assertEmpty($this->queuedBatches, 'Expected no batches queued, but some were.');
    }

    // =========================================================================
    // Recorded call accessors
    // =========================================================================

    public function recordedScrapes(): array        { return $this->scrapes; }
    public function recordedTasks(): array          { return $this->queuedTasks; }
    public function recordedBatches(): array        { return $this->queuedBatches; }
    public function recordedStatusChecks(): array   { return $this->statusChecks; }
    public function recordedResultFetches(): array  { return $this->resultFetches; }

    // =========================================================================
    // Internal sync-client adapter
    // =========================================================================

    private function asSyncClient(): DecodoClient
    {
        $fake = $this;

        return new class ($fake) extends DecodoClient {
            public function __construct(private DecodoFake $fake) {}

            public function scrape(string $url, array $options = []): ScrapeResult
            {
                return $this->scrapeMany([$url], $options)->first();
            }

            public function scrapeMany(array $urls, array $options = []): Collection
            {
                $this->fake->scrapes[] = ['urls' => $urls, 'options' => $options, 'url' => $urls[0] ?? ''];
                return $this->fake->popScrapeResponse($urls);
            }

            public function scrapeWithJs(string $url, array $options = []): ScrapeResult
            {
                return $this->scrape($url, array_merge($options, ['headless' => 'html']));
            }

            public function screenshot(string $url, array $options = []): ScrapeResult
            {
                return $this->scrape($url, array_merge($options, ['headless' => 'png']));
            }

            public function scrapeFromGeo(string $url, string $geo, array $options = []): ScrapeResult
            {
                return $this->scrape($url, array_merge($options, ['geo' => $geo]));
            }

            public function scrapeAsMarkdown(string $url, array $options = []): ScrapeResult
            {
                return $this->scrape($url, array_merge($options, ['markdown' => true]));
            }

            public function scrapeWithParser(string $target, string $url, array $options = []): ScrapeResult
            {
                return $this->scrape($url, array_merge($options, ['target' => $target, 'parse' => true]));
            }

            public function send(\Rkdhatterwal\DecodoScraper\PayloadBuilder $builder): Collection
            {
                $payload = $builder->build();
                $url     = $payload['url'] ?? '';
                return $this->scrapeMany([$url]);
            }
        };
    }

    // =========================================================================
    // Internal async-client adapter
    // =========================================================================

    private function asAsyncClient(): AsyncDecodoClient
    {
        $fake = $this;

        return new class ($fake) extends AsyncDecodoClient {
            public function __construct(private DecodoFake $fake) {}

            public function queueTask(
                string $url,
                array $options = [],
                ?string $callbackUrl = null,
                ?string $passthrough = null,
                ?\Illuminate\Database\Eloquent\Model $scrapeable = null,
            ): TaskResponse {
                $this->fake->queuedTasks[] = compact('url', 'options', 'callbackUrl', 'passthrough');
                return TaskResponse::fromArray($this->fake->popTaskResponse($url));
            }

            public function queueTaskWithBuilder(
                \Rkdhatterwal\DecodoScraper\PayloadBuilder $builder,
                ?\Illuminate\Database\Eloquent\Model $scrapeable = null,
            ): TaskResponse {
                $payload = $builder->build();
                return $this->queueTask($payload['url'] ?? '');
            }

            public function queueBatch(
                array $urls,
                array $options = [],
                ?string $callbackUrl = null,
                ?string $passthrough = null,
                ?string $batchName = null,
            ): BatchTaskResponse {
                $this->fake->queuedBatches[] = compact('urls', 'options', 'callbackUrl', 'passthrough', 'batchName');
                return $this->fake->popBatchResponse($urls);
            }

            public function getTaskStatus(string $taskId): TaskResponse
            {
                $this->fake->statusChecks[] = $taskId;
                return TaskResponse::fromArray($this->fake->getStatusResponse($taskId));
            }

            public function getTaskResults(string $taskId): Collection
            {
                $this->fake->resultFetches[] = $taskId;
                return $this->fake->getResultResponse($taskId);
            }

            public function getFirstTaskResult(string $taskId): ScrapeResult
            {
                return $this->getTaskResults($taskId)->first()
                    ?? throw DecodoException::emptyResponse();
            }

            public function pollUntilDone(string $taskId, int $intervalMs = 2000, int $maxAttempts = 30): Collection
            {
                return $this->getTaskResults($taskId);
            }
        };
    }

    // =========================================================================
    // Response pop/peek helpers
    // =========================================================================

    private function popScrapeResponse(array $urls): Collection
    {
        if (! empty($this->scrapeResponses)) {
            $data = array_shift($this->scrapeResponses);
            return collect([$data])->map(fn ($r) => ScrapeResult::fromArray($r));
        }

        if ($this->failOnUnstubbed) {
            throw DecodoException::emptyResponse();
        }

        return collect([ScrapeResult::fromArray($this->makeResult('<html></html>', 200, '', $urls[0] ?? ''))]);
    }

    private function popTaskResponse(string $url): array
    {
        if (! empty($this->taskResponses)) {
            return array_shift($this->taskResponses);
        }

        if ($this->failOnUnstubbed) {
            throw new DecodoException('No stubbed task response available.');
        }

        return $this->makeTaskData('fake-task-' . uniqid(), 'pending', $url);
    }

    private function popBatchResponse(array $urls): BatchTaskResponse
    {
        if (! empty($this->batchResponses)) {
            $ids   = array_shift($this->batchResponses);
            $tasks = array_map(fn ($id, $url) => $this->makeTaskData($id, 'pending', $url ?? ''), $ids, $urls);
            return BatchTaskResponse::fromArray($tasks);
        }

        if ($this->failOnUnstubbed) {
            throw new DecodoException('No stubbed batch response available.');
        }

        $tasks = array_map(fn ($url) => $this->makeTaskData('fake-task-' . uniqid(), 'pending', $url), $urls);
        return BatchTaskResponse::fromArray($tasks);
    }

    private function getStatusResponse(string $taskId): array
    {
        if (isset($this->statusResponses[$taskId])) {
            return $this->statusResponses[$taskId];
        }

        if ($this->failOnUnstubbed) {
            throw new DecodoException("No stubbed status for task [{$taskId}].");
        }

        return $this->makeTaskData($taskId, 'done');
    }

    private function getResultResponse(string $taskId): Collection
    {
        if (isset($this->resultResponses[$taskId])) {
            return $this->resultResponses[$taskId];
        }

        if ($this->failOnUnstubbed) {
            throw DecodoException::emptyResponse();
        }

        return collect([ScrapeResult::fromArray($this->makeResult('<html></html>', 200, $taskId))]);
    }

    // =========================================================================
    // Data builders
    // =========================================================================

    private function makeResult(string $content, int $statusCode, string $taskId = '', string $url = 'https://example.com'): array
    {
        return [
            'content'     => $content,
            'status_code' => $statusCode,
            'url'         => $url,
            'task_id'     => $taskId ?: ('fake-task-' . uniqid()),
            'created_at'  => now()->toDateTimeString(),
            'updated_at'  => now()->toDateTimeString(),
        ];
    }

    private function makeTaskData(string $taskId, string $status = 'pending', string $url = 'https://example.com'): array
    {
        return [
            'id'                      => $taskId,
            'status'                  => $status,
            'url'                     => $url,
            'created_at'              => now()->toDateTimeString(),
            'updated_at'              => now()->toDateTimeString(),
            'target'                  => null,
            'query'                   => null,
            'device_type'             => 'desktop',
            'headless'                => null,
            'parse'                   => false,
            'force_headers'           => false,
            'force_cookies'           => false,
            'geo'                     => null,
            'locale'                  => null,
            'domain'                  => 'com',
            'http_method'             => 'get',
            'session_id'              => null,
            'headers'                 => [],
            'cookies'                 => [],
            'successful_status_codes' => [],
            'payload'                 => null,
            'callback_url'            => null,
            'passthrough'             => null,
        ];
    }
}
