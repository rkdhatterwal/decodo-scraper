<?php

namespace Rkdhatterwal\DecodoScraper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Fired during decodo:prune for every stuck-pending task that is being
 * deleted because Decodo's 24-hour result window has long since passed.
 *
 * Use this event to:
 *   - Log the expiry for audit trails
 *   - Alert your team (Slack, email, etc.)
 *   - Automatically trigger a retry via RetryDecodoTasks
 *
 * Example listener:
 *
 *   class NotifyTeamOnExpiry
 *   {
 *       public function handle(DecodoTaskExpired $event): void
 *       {
 *           Notification::route('slack', config('services.slack.webhook'))
 *               ->notify(new DecodoTaskExpiredNotification($event->task));
 *       }
 *   }
 */
class DecodoTaskExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        /** The task that is about to be deleted due to expiry. */
        public readonly DecodoTask $task,

        /**
         * Why the task is considered expired.
         * Values: 'pending_timeout' | 'result_window_exceeded'
         */
        public readonly string $reason,
    ) {}
}
