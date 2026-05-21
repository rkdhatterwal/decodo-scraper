<?php

namespace Rkdhatterwal\DecodoScraper;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Routes package log messages to the channel configured at decodo.logging.channel.
 *
 * When the channel is null, the application's default log channel is used.
 * When 'decodo' is set, add a matching channel to config/logging.php:
 *
 *   'decodo' => [
 *       'driver' => 'daily',
 *       'path'   => storage_path('logs/decodo.log'),
 *       'level'  => 'debug',
 *       'days'   => 14,
 *   ],
 */
class DecodoLogger
{
    private static function channel(): LoggerInterface
    {
        $channel = config('decodo.logging.channel');

        return $channel ? Log::channel($channel) : Log::channel();
    }

    public static function info(string $message, array $context = []): void
    {
        static::channel()->info("[Decodo] {$message}", $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        static::channel()->warning("[Decodo] {$message}", $context);
    }

    public static function error(string $message, array $context = []): void
    {
        static::channel()->error("[Decodo] {$message}", $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        static::channel()->debug("[Decodo] {$message}", $context);
    }
}
