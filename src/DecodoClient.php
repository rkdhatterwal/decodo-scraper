<?php

namespace Rkdhatterwal\DecodoScraper;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult;
use Rkdhatterwal\DecodoScraper\Exceptions\DecodoException;

/**
 * Decodo Real-time Scraping Client
 *
 * Wraps the v2 synchronous /scrape endpoint.
 * For async task-based scraping see AsyncDecodoClient.
 *
 * Docs: https://help.decodo.com/docs/web-scraping-api-real-time-requests
 */
class DecodoClient
{
    private string $baseUrl;
    private int $timeout;
    private array $defaults;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $token,
        array $config = [],
    ) {
        if (empty($this->token)) {
            throw DecodoException::missingToken();
        }

        $this->baseUrl  = rtrim($config['base_url'] ?? 'https://scraper-api.decodo.com/v2', '/');
        $this->timeout  = $config['timeout']  ?? 120;
        $this->defaults = $config['defaults'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Convenience methods
    // -------------------------------------------------------------------------

    /**
     * Scrape a single URL and return the first result.
     *
     * @param  array<string, mixed>  $options  Any payload parameters (see PayloadBuilder).
     * @throws ConnectionException
     */
    public function scrape(string $url, array $options = []): ScrapeResult
    {
        $results = $this->scrapeMany([$url], $options);

        if ($results->isEmpty()) {
            throw DecodoException::emptyResponse();
        }

        return $results->first();
    }

    /**
     * Scrape multiple URLs in a single request and return a Collection of results.
     *
     * @param  string[]  $urls
     * @param  array<string, mixed>  $options
     * @return Collection<int, ScrapeResult>
     * @throws ConnectionException
     */
    public function scrapeMany(array $urls, array $options = []): Collection
    {
        if (count($urls) === 1) {
            $payload = PayloadBuilder::fromDefaults($this->defaults, $options)
                ->url($urls[0])
                ->build();
        } else {
            $payload = PayloadBuilder::fromDefaults($this->defaults, $options)
                ->buildBatch($urls);
        }

        return $this->sendScrapeRequest($payload);
    }

    /**
     * Scrape with JavaScript rendering enabled (headless browser, html mode).
     */
    public function scrapeWithJs(string $url, array $options = []): ScrapeResult
    {
        return $this->scrape($url, array_merge($options, ['headless' => 'html']));
    }

    /**
     * Capture a full-page screenshot (headless png mode).
     */
    public function screenshot(string $url, array $options = []): ScrapeResult
    {
        return $this->scrape($url, array_merge($options, ['headless' => 'png']));
    }

    /**
     * Scrape a URL from a specific geographic location.
     *
     * @param  string  $geo  e.g. "United States", "Germany"
     */
    public function scrapeFromGeo(string $url, string $geo, array $options = []): ScrapeResult
    {
        return $this->scrape($url, array_merge($options, ['geo' => $geo]));
    }

    /**
     * Scrape and return parsed Markdown instead of raw HTML.
     * Ideal for feeding results into LLM pipelines.
     */
    public function scrapeAsMarkdown(string $url, array $options = []): ScrapeResult
    {
        return $this->scrape($url, array_merge($options, ['markdown' => true]));
    }

    /**
     * Scrape and retrieve structured data using a target template's parser.
     *
     * @param  string  $target  e.g. "amazon_pricing", "google_search"
     */
    public function scrapeWithParser(string $target, string $url, array $options = []): ScrapeResult
    {
        return $this->scrape($url, array_merge($options, [
            'target' => $target,
            'parse'  => true,
        ]));
    }

    // -------------------------------------------------------------------------
    // Builder-based entry point for full control
    // -------------------------------------------------------------------------

    /**
     * Execute a request built manually with PayloadBuilder.
     *
     * @return Collection<int, ScrapeResult>
     * @throws ConnectionException
     */
    public function send(PayloadBuilder $builder): Collection
    {
        return $this->sendScrapeRequest($builder->build());
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * @return Collection<int, ScrapeResult>
     * @throws ConnectionException
     */
    private function sendScrapeRequest(array $payload): Collection
    {
        $response = $this->http
            ->withToken($this->token, 'Basic')
            ->timeout($this->timeout)
            ->acceptJson()
            ->post("{$this->baseUrl}/scrape", $payload);

        if ($response->failed()) {
            throw DecodoException::requestFailed($response->status(), $response->body());
        }

        $body = $response->json();

        if (empty($body['results'])) {
            throw DecodoException::emptyResponse();
        }

        return collect($body['results'])->map(
            fn (array $result) => ScrapeResult::fromArray($result)
        );
    }
}
