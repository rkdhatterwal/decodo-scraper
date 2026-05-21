<?php

namespace Rkdhatterwal\DecodoScraper\Facades;

use Illuminate\Support\Facades\Facade;
use Rkdhatterwal\DecodoScraper\AsyncDecodoClient;

/**
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\TaskResponse      queueTask(string $url, array $options = [], ?string $callbackUrl = null, ?string $passthrough = null)
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\TaskResponse      queueTaskWithBuilder(\Rkdhatterwal\DecodoScraper\PayloadBuilder $builder)
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\BatchTaskResponse queueBatch(array $urls, array $options = [], ?string $callbackUrl = null, ?string $passthrough = null)
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\TaskResponse      getTaskStatus(string $taskId)
 * @method static \Illuminate\Support\Collection                   getTaskResults(string $taskId)
 * @method static \Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult      getFirstTaskResult(string $taskId)
 * @method static \Illuminate\Support\Collection                   pollUntilDone(string $taskId, int $intervalMs = 2000, int $maxAttempts = 30)
 *
 * @see \Rkdhatterwal\DecodoScraper\AsyncDecodoClient
 */
class DecodoAsync extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AsyncDecodoClient::class;
    }
}
