<?php

namespace Rkdhatterwal\DecodoScraper;

use Rkdhatterwal\DecodoScraper\Exceptions\DecodoException;

/**
 * Builds and validates a Decodo API request payload.
 *
 * Supported parameters are documented at:
 * https://help.decodo.com/docs/web-scraping-api-parameters
 */
class PayloadBuilder
{
    // Valid values per documented parameter options
    private const VALID_PROXY_POOLS   = ['premium', 'standard'];
    private const VALID_DEVICE_TYPES  = ['desktop', 'mobile', 'tablet'];
    private const VALID_HEADLESS      = ['html', 'png'];
    private const VALID_HTTP_METHODS  = ['get', 'post'];

    private array $payload = [];

    // -------------------------------------------------------------------------
    // Required / core
    // -------------------------------------------------------------------------

    /**
     * Set the URL to scrape. Mutually exclusive with query().
     */
    public function url(string $url): static
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw DecodoException::invalidUrl($url);
        }

        unset($this->payload['query']);
        $this->payload['url'] = $url;

        return $this;
    }

    /**
     * Set a search query (used with target templates like google_search).
     * Mutually exclusive with url().
     */
    public function query(string $query): static
    {
        if (empty(trim($query))) {
            throw new \InvalidArgumentException('Query cannot be empty.');
        }

        unset($this->payload['url']);
        $this->payload['query'] = $query;

        return $this;
    }

    /**
     * Set a scraping target template (e.g. "google_search", "amazon_pricing").
     */
    public function target(string $target): static
    {
        $this->payload['target'] = $target;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Optional parameters
    // -------------------------------------------------------------------------

    /**
     * Proxy pool: "premium" (default) handles complex anti-bot;
     * "standard" for simple pages.
     */
    public function proxyPool(string $pool): static
    {
        if (! in_array(strtolower($pool), self::VALID_PROXY_POOLS, true)) {
            throw new \InvalidArgumentException(
                'Invalid proxy_pool. Allowed: ' . implode(', ', self::VALID_PROXY_POOLS)
            );
        }

        $this->payload['proxy_pool'] = strtolower($pool);
        return $this;
    }

    /**
     * Enable JavaScript rendering ("html") or screenshot ("png").
     */
    public function headless(string $mode = 'html'): static
    {
        if (! in_array(strtolower($mode), self::VALID_HEADLESS, true)) {
            throw new \InvalidArgumentException(
                'Invalid headless mode. Allowed: ' . implode(', ', self::VALID_HEADLESS)
            );
        }

        $this->payload['headless'] = strtolower($mode);
        return $this;
    }

    /**
     * Geographical location, e.g. "United States", "Germany".
     */
    public function geo(string $geo): static
    {
        $this->payload['geo'] = $geo;
        return $this;
    }

    /**
     * Top-level domain, e.g. "com", "co.uk", "fr".
     */
    public function domain(string $domain): static
    {
        $this->payload['domain'] = ltrim($domain, '.');
        return $this;
    }

    /**
     * Browser locale, e.g. "en-US", "en-GB".
     */
    public function locale(string $locale): static
    {
        $this->payload['locale'] = $locale;
        return $this;
    }

    /**
     * Custom request headers forwarded to the target.
     *
     * @param  array<string, string>  $headers
     */
    public function headers(array $headers): static
    {
        $this->payload['headers'] = $headers;
        return $this;
    }

    /**
     * Force user-provided headers to be forwarded (overrides default behaviour).
     */
    public function forceHeaders(bool $force = true): static
    {
        $this->payload['force_headers'] = $force;
        return $this;
    }

    /**
     * Client cookies, e.g. for authenticated page scraping.
     *
     * Supports:
     *  - Associative array: ['foo' => 'bar']
     *  - List of objects with key/value: [['key' => 'foo', 'value' => 'bar']]
     *  - List of objects with name/value: [['name' => 'foo', 'value' => 'bar']]
     */
    public function cookies(array $cookies): static
    {
        $normalized = [];

        foreach ($cookies as $k => $v) {
            // Case 1: [['name' => 'foo', 'value' => 'bar']] or [['key' => 'foo', ...]]
            if (is_array($v) && (isset($v['name']) || isset($v['key']))) {
                $normalized[] = [
                    'key'   => (string) ($v['key'] ?? $v['name']),
                    'value' => (string) ($v['value'] ?? ''),
                ];
                continue;
            }

            // Case 2: ['foo' => 'bar']
            $normalized[] = [
                'key'   => (string) $k,
                'value' => (string) $v,
            ];
        }

        $this->payload['cookies'] = $normalized;

        return $this;
    }

    /**
     * Force user-provided cookies to be forwarded.
     */
    public function forceCookies(bool $force = true): static
    {
        $this->payload['force_cookies'] = $force;
        return $this;
    }

    /**
     * Device type: "desktop" (default), "mobile", or "tablet".
     */
    public function deviceType(string $type): static
    {
        if (! in_array(strtolower($type), self::VALID_DEVICE_TYPES, true)) {
            throw new \InvalidArgumentException(
                'Invalid device_type. Allowed: ' . implode(', ', self::VALID_DEVICE_TYPES)
            );
        }

        $this->payload['device_type'] = strtolower($type);
        return $this;
    }

    /**
     * Enable structured data parsing (requires a target template with a parser).
     */
    public function parse(bool $parse = true): static
    {
        $this->payload['parse'] = $parse;
        return $this;
    }

    /**
     * Reuse the same proxy IP across requests for up to 10 minutes.
     */
    public function sessionId(string|int $id): static
    {
        $this->payload['session_id'] = (string) $id;
        return $this;
    }

    /**
     * Use POST instead of GET as the HTTP method.
     */
    public function httpMethod(string $method): static
    {
        if (! in_array(strtolower($method), self::VALID_HTTP_METHODS, true)) {
            throw new \InvalidArgumentException(
                'Invalid http_method. Allowed: ' . implode(', ', self::VALID_HTTP_METHODS)
            );
        }

        $this->payload['http_method'] = strtolower($method);
        return $this;
    }

    /**
     * Base64-encoded POST body (used when http_method is POST).
     */
    public function payload(string $body): static
    {
        $this->payload['payload'] = base64_encode($body);
        return $this;
    }

    /**
     * Additional HTTP status codes to treat as successful.
     *
     * @param  int[]  $codes
     */
    public function successfulStatusCodes(array $codes): static
    {
        foreach ($codes as $code) {
            if (! is_int($code) || $code < 100 || $code > 599) {
                throw new \InvalidArgumentException("Invalid HTTP status code: {$code}");
            }
        }

        $this->payload['successful_status_codes'] = $codes;
        return $this;
    }

    /**
     * Convert HTML output to Markdown (ideal for LLM pipelines).
     */
    public function markdown(bool $enabled = true): static
    {
        $this->payload['markdown'] = $enabled;
        return $this;
    }

    /**
     * Retrieve XHR/fetch requests made by the page.
     */
    public function xhr(bool $enabled = true): static
    {
        $this->payload['xhr'] = $enabled;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Async-specific
    // -------------------------------------------------------------------------

    /**
     * URL to receive a POST callback once the async task completes.
     */
    public function callbackUrl(string $url): static
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw DecodoException::invalidUrl($url);
        }

        $this->payload['callback_url'] = $url;
        return $this;
    }

    /**
     * Passthrough value echoed back in the callback for origin verification.
     */
    public function passthrough(string $value): static
    {
        $this->payload['passthrough'] = $value;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Build
    // -------------------------------------------------------------------------

    /**
     * Validate and return the assembled payload array.
     *
     * @throws \InvalidArgumentException  When neither url nor query is set.
     * @throws DecodoException            When the url is invalid.
     */
    public function build(): array
    {
        $this->validate();

        $payload = $this->applyPremiumDomainDetection($this->payload, $this->payload['url'] ?? null);

        return $this->applyStandardPoolFiltering($payload);
    }

    /**
     * Build a batch payload by wrapping the url as an array.
     *
     * @param  string[]  $urls
     */
    public function buildBatch(array $urls): array
    {
        foreach ($urls as $url) {
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                throw DecodoException::invalidUrl($url);
            }
        }

        if (empty($urls)) {
            throw new \InvalidArgumentException('Batch request must contain at least one URL.');
        }

        $base = $this->payload;
        unset($base['url'], $base['query']);
        $base['url'] = $urls;

        $base = $this->applyPremiumDomainDetection($base, $urls);

        return $this->applyStandardPoolFiltering($base);
    }

    /**
     * Merge external defaults (from config) and override with explicit options.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     */
    public static function fromDefaults(array $defaults, array $overrides = []): static
    {
        $builder = new static();

        foreach (array_merge($defaults, $overrides) as $key => $value) {
            if (is_null($value) || $value === false || $value === [] || $value === '') {
                continue;
            }

            match ($key) {
                'url'                     => $builder->url($value),
                'query'                   => $builder->query($value),
                'target'                  => $builder->target($value),
                'proxy_pool'              => $builder->proxyPool($value),
                'headless'                => $builder->headless($value),
                'geo'                     => $builder->geo($value),
                'domain'                  => $builder->domain($value),
                'locale'                  => $builder->locale($value),
                'headers'                 => $builder->headers($value),
                'force_headers'           => $builder->forceHeaders($value),
                'cookies'                 => $builder->cookies($value),
                'force_cookies'           => $builder->forceCookies($value),
                'device_type'             => $builder->deviceType($value),
                'parse'                   => $builder->parse($value),
                'session_id'              => $builder->sessionId($value),
                'http_method'             => $builder->httpMethod($value),
                'payload'                 => null, // skip; requires explicit base64 encoding
                'successful_status_codes' => $builder->successfulStatusCodes($value),
                'markdown'                => $builder->markdown($value),
                'xhr'                     => $builder->xhr($value),
                'callback_url'            => $builder->callbackUrl($value),
                'passthrough'             => $builder->passthrough($value),
                default                   => null,
            };
        }

        return $builder;
    }

    /**
     * Apply filtering for standard proxy pool.
     */
    private function applyStandardPoolFiltering(array $payload): array
    {
        if (($payload['proxy_pool'] ?? '') === 'standard') {
            $allowed = ['proxy_pool', 'url', 'headless', 'geo'];

            if (($payload['http_method'] ?? 'get') === 'post') {
                $allowed[] = 'http_method';
                $allowed[] = 'payload';
            }

            return array_intersect_key($payload, array_flip($allowed));
        }

        return $payload;
    }

    /**
     * Apply domain auto-detection for premium proxy pool.
     */
    private function applyPremiumDomainDetection(array $payload, string|array|null $urls): array
    {
        if (($payload['proxy_pool'] ?? 'premium') !== 'premium') {
            return $payload;
        }

        if (empty($urls)) {
            return $payload;
        }

        if (is_string($urls)) {
            $tld = $this->extractTld($urls);
            if ($tld) {
                $payload['domain'] = $tld;
            }
        } elseif (is_array($urls)) {
            $tlds = array_unique(array_filter(array_map([$this, 'extractTld'], $urls)));
            if (count($tlds) === 1) {
                $payload['domain'] = reset($tlds);
            } else {
                unset($payload['domain']);
            }
        }

        return $payload;
    }

    /**
     * Extract the TLD from a URL.
     */
    private function extractTld(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        $parts = explode('.', $host);
        $count = count($parts);

        if ($count < 2) {
            return null;
        }

        // Check for common multi-part TLDs like .co.uk, .com.au, etc.
        if ($count >= 3) {
            $last = $parts[$count - 1];
            $penultimate = $parts[$count - 2];

            // Common 2nd level TLDs that precede a country code
            $common2ndLevel = ['com', 'co', 'net', 'org', 'gov', 'edu', 'ac', 'biz', 'info'];
            if (strlen($last) === 2 && in_array($penultimate, $common2ndLevel)) {
                return $penultimate . '.' . $last;
            }
        }

        return $parts[$count - 1];
    }

    private function validate(): void
    {
        $hasUrl   = isset($this->payload['url'])   && is_string($this->payload['url']);
        $hasQuery = isset($this->payload['query'])  && is_string($this->payload['query']);

        if (! $hasUrl && ! $hasQuery) {
            throw new \InvalidArgumentException(
                'A url or query parameter is required for every Decodo request.'
            );
        }

        // POST requires a payload body
        if (($this->payload['http_method'] ?? 'get') === 'post' && empty($this->payload['payload'])) {
            throw new \InvalidArgumentException(
                'A base64-encoded payload body is required when http_method is POST. Use ->payload($body).'
            );
        }
    }
}
