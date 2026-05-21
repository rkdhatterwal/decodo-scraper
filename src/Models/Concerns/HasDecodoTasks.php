<?php

namespace Rkdhatterwal\DecodoScraper\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Add this trait to any Eloquent model you want to associate with scrape tasks.
 *
 * Usage in your app:
 *
 *   use Rkdhatterwal\DecodoScraper\Models\Concerns\HasDecodoTasks;
 *
 *   class Product extends Model
 *   {
 *       use HasDecodoTasks;
 *   }
 *
 *   // Then:
 *   $product->decodoTasks;                   // All tasks for this product
 *   $product->pendingDecodoTasks;            // Tasks still in progress
 *   $product->completedDecodoTasks;          // Successfully scraped tasks
 *   $product->scrape('https://...');         // Queue a new task for this model
 */
trait HasDecodoTasks
{
    public function decodoTasks(): MorphMany
    {
        return $this->morphMany(DecodoTask::class, 'scrapeable');
    }

    public function pendingDecodoTasks(): MorphMany
    {
        return $this->morphMany(DecodoTask::class, 'scrapeable')
            ->where('status', 'pending');
    }

    public function completedDecodoTasks(): MorphMany
    {
        return $this->morphMany(DecodoTask::class, 'scrapeable')
            ->where('status', 'done');
    }

    public function faultedDecodoTasks(): MorphMany
    {
        return $this->morphMany(DecodoTask::class, 'scrapeable')
            ->where('status', 'faulted');
    }

    /**
     * Queue a new scrape task for this model using the package's async client.
     *
     * @param  string                $url
     * @param  array<string, mixed>  $options
     * @param  string|null           $callbackUrl
     */
    public function queueScrape(
        string $url,
        array $options = [],
        ?string $callbackUrl = null,
    ): DecodoTask {
        /** @var \Rkdhatterwal\DecodoScraper\AsyncDecodoClient $client */
        $client   = app('decodo.async');
        $response = $client->queueTask($url, $options, $callbackUrl);

        return $this->decodoTasks()->create([
            'decodo_task_id' => $response->id,
            'url'            => $url,
            'status'         => 'pending',
            'options'        => $options,
            'callback_url'   => $callbackUrl,
            'queued_at'      => now(),
        ]);
    }
}
