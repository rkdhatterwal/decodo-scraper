<?php

namespace Rkdhatterwal\DecodoScraper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rkdhatterwal\DecodoScraper\Models\DecodoBatch;

/**
 * Fired when all tasks within a batch have reached a terminal state
 * (done, faulted, or a mix — see $batch->status for the aggregate result).
 *
 * Status values on the batch:
 *   "done"    — every task completed successfully
 *   "faulted" — every task faulted
 *   "partial" — a mix of done and faulted tasks
 */
class DecodoBatchCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        /** The batch model with recalculated aggregate status. */
        public readonly DecodoBatch $batch,
    ) {}
}
