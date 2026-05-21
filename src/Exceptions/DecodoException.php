<?php

namespace Rkdhatterwal\DecodoScraper\Exceptions;

use RuntimeException;

class DecodoException extends RuntimeException
{
    public static function missingToken(): self
    {
        return new self(
            'Decodo API token is not set. Add DECODO_TOKEN to your .env file.'
        );
    }

    public static function invalidUrl(string $url): self
    {
        return new self("The provided URL is invalid: [{$url}]");
    }

    public static function requestFailed(int $status, string $body): self
    {
        return new self(
            "Decodo API request failed with HTTP {$status}: {$body}"
        );
    }

    public static function emptyResponse(): self
    {
        return new self('Decodo API returned an empty or malformed response.');
    }
}
