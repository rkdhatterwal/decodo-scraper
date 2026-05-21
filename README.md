# Decodo Scraper for Laravel

A clean, well-tested Laravel wrapper for the [Decodo Web Scraping API](https://decodo.com/scraping/web) — supports both **real-time** (v2) and **async/batch** (v3) scraping.

---

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require rkdhatterwal/decodo-scraper
```

Publish the config file:

```bash
php artisan vendor:publish --tag=decodo-config
```

Add credentials to `.env`:

```env
DECODO_TOKEN=your_basic_auth_token_here
DECODO_TIMEOUT=120
```

> Your token is in the [Decodo dashboard](https://dashboard.decodo.com) under **Scraping APIs → Username / Token**.

---

## Real-time Scraping (v2)

Use the `Decodo` facade or inject `DecodoClient`.

```php
use Rkdhatterwal\DecodoScraper\Facades\Decodo;

// Simple scrape → ScrapeResult
$result = Decodo::scrape('https://example.com');
echo $result->content;     // raw HTML
echo $result->statusCode;  // upstream HTTP status

// JavaScript rendering
$result = Decodo::scrapeWithJs('https://example.com');

// Screenshot (PNG)
$result = Decodo::screenshot('https://example.com');

// Geo-targeted
$result = Decodo::scrapeFromGeo('https://example.com', 'United States');

// Return Markdown (great for LLM pipelines)
$result = Decodo::scrapeAsMarkdown('https://example.com');

// Structured data via a target template's parser
$result = Decodo::scrapeWithParser('amazon_pricing', 'https://amazon.com/dp/B0BS1QCF');

// Scrape multiple URLs at once
$results = Decodo::scrapeMany(['https://example.com', 'https://another.com']);
```

### Full control with PayloadBuilder

```php
use Rkdhatterwal\DecodoScraper\PayloadBuilder;
use Rkdhatterwal\DecodoScraper\Facades\Decodo;

$results = Decodo::send(
    (new PayloadBuilder())
        ->url('https://example.com')
        ->headless('html')
        ->geo('Germany')
        ->locale('de-DE')
        ->deviceType('mobile')
        ->proxyPool('premium')
        ->markdown()
        ->successfulStatusCodes([200, 301])
);
```

---

## Async Scraping (v3)

Use the `DecodoAsync` facade or inject `AsyncDecodoClient`.

### Queue a single task

```php
use Rkdhatterwal\DecodoScraper\Facades\DecodoAsync;

// Queue and get a task ID immediately
$task = DecodoAsync::queueTask('https://example.com');
echo $task->id;      // "7434928397127555073"
echo $task->status;  // "pending"

// With a webhook callback
$task = DecodoAsync::queueTask(
    url:         'https://example.com',
    options:     ['headless' => 'html', 'geo' => 'United States'],
    callbackUrl: 'https://my.app/webhook/decodo',
    passthrough: 'my-verification-secret',   // echoed back for auth
);
```

### Queue a batch

```php
$batch = DecodoAsync::queueBatch(
    urls:        ['https://site1.com', 'https://site2.com', 'https://site3.com'],
    options:     ['geo' => 'United States'],
    callbackUrl: 'https://my.app/webhook/decodo-batch',
);

$batch->ids();   // Collection of task IDs
$batch->count(); // 3
```

### Check status & retrieve results

```php
// Poll status manually
$status = DecodoAsync::getTaskStatus($task->id);
$status->isPending();  // true / false
$status->isDone();     // true / false
$status->isFaulted();  // true / false

// Retrieve results once done (valid for 24 hours)
$results = DecodoAsync::getTaskResults($task->id);  // Collection<ScrapeResult>
$result  = DecodoAsync::getFirstTaskResult($task->id);  // ScrapeResult

// Convenience: poll and block until done (for scripts/queues)
$results = DecodoAsync::pollUntilDone($task->id, intervalMs: 2000, maxAttempts: 30);
```

---

## ScrapeResult DTO

| Property     | Type     | Description                           |
|--------------|----------|---------------------------------------|
| `content`    | `string` | Raw HTML, Markdown, or parsed JSON    |
| `statusCode` | `int`    | HTTP status of the upstream page      |
| `url`        | `string` | The URL that was scraped              |
| `taskId`     | `string` | Decodo task ID                        |
| `createdAt`  | `string` | Task creation timestamp               |
| `updatedAt`  | `string` | Task completion timestamp             |

```php
$result->isSuccessful(); // true when statusCode is 2xx
$result->toArray();
```

## TaskResponse DTO

Returned by `queueTask()`, `queueBatch()`, `getTaskStatus()`.

```php
$task->id;          // Task ID for later retrieval
$task->status;      // "pending" | "done" | "faulted"
$task->isPending(); // bool
$task->isDone();    // bool
$task->isFaulted(); // bool
$task->toArray();
```

---

## All Payload Parameters

See the [Decodo parameters docs](https://help.decodo.com/docs/web-scraping-api-parameters).

| Method on PayloadBuilder       | API Parameter             | Default    |
|-------------------------------|---------------------------|------------|
| `->url($url)`                 | `url`                     | required   |
| `->query($q)`                 | `query`                   | —          |
| `->target($t)`                | `target`                  | null       |
| `->proxyPool('standard')`     | `proxy_pool`              | `premium`  |
| `->headless('html'/'png')`    | `headless`                | null       |
| `->geo('United States')`      | `geo`                     | auto       |
| `->domain('co.uk')`           | `domain`                  | `com`      |
| `->locale('en-GB')`           | `locale`                  | matched    |
| `->headers([...])`            | `headers`                 | null       |
| `->forceHeaders()`            | `force_headers`           | false      |
| `->cookies([...])`            | `cookies`                 | null       |
| `->forceCookies()`            | `force_cookies`           | false      |
| `->deviceType('mobile')`      | `device_type`             | `desktop`  |
| `->parse()`                   | `parse`                   | false      |
| `->sessionId('1234')`         | `session_id`              | null       |
| `->httpMethod('post')`        | `http_method`             | `get`      |
| `->payload($body)`            | `payload` (base64)        | null       |
| `->successfulStatusCodes([])` | `successful_status_codes` | null       |
| `->markdown()`                | `markdown`                | false      |
| `->xhr()`                     | `xhr`                     | false      |
| `->callbackUrl($url)`         | `callback_url`            | null       |
| `->passthrough($val)`         | `passthrough`             | null       |

---

## Testing

```bash
composer test
```

All tests use `Http::fake()` — no live requests are made.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT
