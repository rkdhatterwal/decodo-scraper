<?php

namespace Rkdhatterwal\DecodoScraper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Fired when a Decodo task webhook is received and the task status is "done".
 *
 * Listen for this event in your app:
 *
 *   use Rkdhatterwal\DecodoScraper\Events\DecodoTaskCompleted;
 *
 *   class AppServiceProvider extends ServiceProvider
 *   {
 *       protected $listen = [
 *           DecodoTaskCompleted::class => [
 *               YourListener::class,
 *           ],
 *       ];
 *   }
 */
class DecodoTaskCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        /** The persisted task model with result_content and result_status_code populated. */
        public readonly DecodoTask $task,

        /** The raw webhook payload received from Decodo for this callback. */
        public readonly array $webhookPayload,
    ) {}
}
