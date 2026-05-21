<?php

namespace Rkdhatterwal\DecodoScraper\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies that an incoming Decodo webhook contains the expected passthrough
 * token, guarding against spoofed callbacks.
 *
 * The passthrough value is set when queuing a task:
 *
 *   DecodoAsync::queueTask($url, [], $callbackUrl, passthrough: 'my-secret');
 *
 * Decodo echoes it back verbatim in the POST body:
 *   { "passthrough": "my-secret", "id": "...", "status": "done", ... }
 *
 * Configure the expected secret in config/decodo.php:
 *
 *   'webhook' => [
 *       'passthrough_secret' => env('DECODO_WEBHOOK_SECRET'),
 *       'verify_passthrough' => true,
 *   ],
 */
class VerifyDecodoWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('decodo.webhook', []);

        // Verification is opt-in. If disabled, pass through immediately.
        if (! ($config['verify_passthrough'] ?? false)) {
            return $next($request);
        }

        $expected = $config['passthrough_secret'] ?? null;

        if (empty($expected)) {
            abort(500, 'Decodo webhook passthrough_secret is not configured.');
        }

        $received = $request->input('passthrough');

        if (! hash_equals($expected, (string) $received)) {
            abort(403, 'Invalid Decodo webhook passthrough token.');
        }

        return $next($request);
    }
}
