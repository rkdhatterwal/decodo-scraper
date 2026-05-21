<?php

namespace Rkdhatterwal\DecodoScraper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rkdhatterwal\DecodoScraper\Models\DecodoTask;

/**
 * Fired when a Decodo task webhook is received and the task status is "faulted".
 *
 * Use this event to retry the task, alert your team, or log the failure.
 */
class DecodoTaskFaulted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        /** The persisted task model marked as faulted. */
        public readonly DecodoTask $task,

        /** The raw webhook payload received from Decodo. */
        public readonly array $webhookPayload,
    ) {}
}
