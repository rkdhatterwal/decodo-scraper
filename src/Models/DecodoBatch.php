<?php

namespace Rkdhatterwal\DecodoScraper\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecodoBatch extends Model
{
    protected $table = 'decodo_batches';

    protected $fillable = [
        'name',
        'total_tasks',
        'status',
        'callback_url',
        'passthrough',
        'options',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'options'      => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function tasks(): HasMany
    {
        return $this->hasMany(DecodoTask::class, 'decodo_batch_id');
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

    public function scopePartial(Builder $query): Builder
    {
        return $query->where('status', 'partial');
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

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    /**
     * Recompute and persist the aggregate status based on child task statuses.
     *
     * Rules:
     *   - All done        → done
     *   - All faulted     → faulted
     *   - Any mix         → partial
     *   - Any still pending → pending (not yet finalised)
     */
    public function recalculateStatus(): void
    {
        $tasks = $this->tasks()->get(['status']);

        if ($tasks->isEmpty()) {
            return;
        }

        $statuses = $tasks->pluck('status');

        if ($statuses->contains('pending')) {
            // At least one task hasn't completed yet — keep pending.
            return;
        }

        $allDone    = $statuses->every(fn ($s) => $s === 'done');
        $allFaulted = $statuses->every(fn ($s) => $s === 'faulted');

        $newStatus = match (true) {
            $allDone    => 'done',
            $allFaulted => 'faulted',
            default     => 'partial',
        };

        $this->update([
            'status'       => $newStatus,
            'completed_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    public function pendingCount(): int
    {
        return $this->tasks()->where('status', 'pending')->count();
    }

    public function doneCount(): int
    {
        return $this->tasks()->where('status', 'done')->count();
    }

    public function faultedCount(): int
    {
        return $this->tasks()->where('status', 'faulted')->count();
    }
}
