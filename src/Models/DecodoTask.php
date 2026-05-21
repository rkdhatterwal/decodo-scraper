<?php

namespace Rkdhatterwal\DecodoScraper\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DecodoTask extends Model
{
    protected $table = 'decodo_tasks';

    protected $fillable = [
        'decodo_task_id',
        'decodo_batch_id',
        'scrapeable_type',
        'scrapeable_id',
        'url',
        'query',
        'status',
        'payload',
        'options',
        'callback_url',
        'passthrough',
        'result_content',
        'result_status_code',
        'webhook_payload',
        'queued_at',
        'completed_at',
    ];

    protected $casts = [
        'payload'         => 'array',
        'options'         => 'array',
        'webhook_payload' => 'array',
        'queued_at'       => 'datetime',
        'completed_at'    => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DecodoBatch::class, 'decodo_batch_id');
    }

    /**
     * Polymorphic relation back to the app model that triggered this scrape.
     *
     * Usage:
     *   $task->scrapeable   → e.g. a Product, Article, or Lead model
     *   $product->decodoTasks()  → on the app model, use MorphMany
     */
    public function scrapeable(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', 'done');
    }

    public function scopeFaulted(Builder $query): Builder
    {
        return $query->where('status', 'faulted');
    }

    public function scopeStandalone(Builder $query): Builder
    {
        return $query->whereNull('decodo_batch_id');
    }

    public function scopeInBatch(Builder $query, int $batchId): Builder
    {
        return $query->where('decodo_batch_id', $batchId);
    }

    public function scopeForScrapeable(Builder $query, Model $model): Builder
    {
        return $query
            ->where('scrapeable_type', $model->getMorphClass())
            ->where('scrapeable_id', $model->getKey());
    }

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isFaulted(): bool
    {
        return $this->status === 'faulted';
    }

    public function isStandalone(): bool
    {
        return is_null($this->decodo_batch_id);
    }

    // -------------------------------------------------------------------------
    // Mutation helpers
    // -------------------------------------------------------------------------

    /**
     * Mark this task as done, persist the scraped content, and record the
     * raw webhook payload for auditing.
     */
    public function markDone(string $content, int $statusCode, array $webhookPayload = []): void
    {
        $this->update([
            'status'              => 'done',
            'result_content'      => $content,
            'result_status_code'  => $statusCode,
            'webhook_payload'     => $webhookPayload,
            'completed_at'        => now(),
        ]);
    }

    /**
     * Mark this task as faulted and store the webhook payload for debugging.
     */
    public function markFaulted(array $webhookPayload = []): void
    {
        $this->update([
            'status'          => 'faulted',
            'webhook_payload' => $webhookPayload,
            'completed_at'    => now(),
        ]);
    }
}
