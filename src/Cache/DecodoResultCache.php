<?php

namespace Rkdhatterwal\DecodoScraper\Cache;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Rkdhatterwal\DecodoScraper\AsyncDecodoClient;
use Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult;

/**
 * Caches completed Decodo task results so repeated calls to getTaskResults()
 * don't hit the Decodo API unnecessarily.
 *
 * Results for a "done" task are immutable — perfect for caching. The default
 * TTL is 23 hours, just under Decodo's 24-hour result-expiry window, so
 * cached entries never outlive the upstream data.
 *
 * Configuration (config/decodo.php):
 *
 *   'cache' => [
 *       'enabled' => true,
 *       'store'   => null,   // null = default Laravel cache store
 *       'ttl'     => 82800,  // seconds (23 hours)
 *       'prefix'  => 'decodo_result',
 *   ],
 *
 * Usage — inject directly or resolve from the container:
 *
 *   $cached  = app(DecodoResultCache::class);
 *   $results = $cached->remember('7434928397127555073');
 *   $cached->forget('7434928397127555073');
 */
class DecodoResultCache
{
    private bool   $enabled;
    private ?string $store;
    private int    $ttl;
    private string $prefix;

    public function __construct(private readonly AsyncDecodoClient $client)
    {
        $cfg           = config('decodo.cache', []);
        $this->enabled = (bool)   ($cfg['enabled'] ?? true);
        $this->store   =          ($cfg['store']   ?? null);
        $this->ttl     = (int)    ($cfg['ttl']     ?? 82_800);   // 23 hours
        $this->prefix  = (string) ($cfg['prefix']  ?? 'decodo_result');
    }

    /**
     * Return cached results if available, otherwise fetch from Decodo and cache.
     *
     * @return Collection<int, ScrapeResult>
     */
    public function remember(string $taskId): Collection
    {
        if (! $this->enabled) {
            return $this->client->getTaskResults($taskId);
        }

        return $this->store()->remember(
            $this->key($taskId),
            $this->ttl,
            fn () => $this->client->getTaskResults($taskId),
        );
    }

    /**
     * Fetch fresh results from Decodo, bypassing and refreshing the cache.
     *
     * @return Collection<int, ScrapeResult>
     */
    public function fresh(string $taskId): Collection
    {
        $results = $this->client->getTaskResults($taskId);

        if ($this->enabled) {
            $this->store()->put($this->key($taskId), $results, $this->ttl);
        }

        return $results;
    }

    /**
     * Remove cached results for a given task ID.
     */
    public function forget(string $taskId): bool
    {
        return $this->enabled
            ? $this->store()->forget($this->key($taskId))
            : false;
    }

    /**
     * Check whether results for a task are currently cached.
     */
    public function has(string $taskId): bool
    {
        return $this->enabled && $this->store()->has($this->key($taskId));
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function key(string $taskId): string
    {
        return "{$this->prefix}:{$taskId}";
    }

    private function store(): \Illuminate\Contracts\Cache\Repository
    {
        return $this->store ? Cache::store($this->store) : Cache::store();
    }
}
