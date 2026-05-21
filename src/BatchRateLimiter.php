<?php

namespace Rkdhatterwal\DecodoScraper;

/**
 * Enforces Decodo's documented 1-request-per-second rate limit for batch
 * submissions by tracking the timestamp of the last POST and sleeping the
 * difference if a subsequent call arrives too quickly.
 *
 * This is a simple in-process guard. For multi-process or queue-based
 * workloads, use Laravel's rate limiter backed by Redis instead.
 */
class BatchRateLimiter
{
    private static ?float $lastBatchAt = null;

    /**
     * Block the current process until it is safe to send another batch request.
     *
     * @param  int  $minIntervalMs  Minimum gap in milliseconds (default 1 000 ms).
     */
    public static function throttle(int $minIntervalMs = 1_000): void
    {
        if (self::$lastBatchAt === null) {
            self::$lastBatchAt = microtime(true);
            return;
        }

        $elapsedMs = (microtime(true) - self::$lastBatchAt) * 1_000;
        $remainMs  = $minIntervalMs - $elapsedMs;

        if ($remainMs > 0) {
            usleep((int) ($remainMs * 1_000));
        }

        self::$lastBatchAt = microtime(true);
    }

    /**
     * Reset the internal clock — useful between test cases.
     */
    public static function reset(): void
    {
        self::$lastBatchAt = null;
    }
}
