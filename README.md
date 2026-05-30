# Decodo Scraper for Laravel

A clean, well-tested Laravel wrapper for the [Decodo Web Scraping API](https://decodo.com/scraping/web) — supports both **real-time** (v2) and **async/batch** (v3) scraping.

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, 12, or 13

## Installation

```bash
composer require rkdhatterwal/decodo-scraper
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=decodo-config
php artisan vendor:publish --tag=decodo-migrations
php artisan migrate
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

## Database Tracking

When `database.enabled` is true (default), the package automatically tracks every async task and batch in your database using the `decodo_tasks` and `decodo_batches` tables.

### Models
- `Rkdhatterwal\DecodoScraper\Models\DecodoTask`
- `Rkdhatterwal\DecodoScraper\Models\DecodoBatch`

You can associate a task with one of your own models (e.g., a `Product` or `Lead`) by passing it to `queueTask`:

```php
$product = Product::find(1);
DecodoAsync::queueTask('https://example.com', scrapeable: $product);

// Later retrieve it
$task = $product->decodoTasks()->latest()->first();
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

### Queue with PayloadBuilder

You can also use the `PayloadBuilder` with async tasks for a more fluent experience:

```php
use Rkdhatterwal\DecodoScraper\PayloadBuilder;
use Rkdhatterwal\DecodoScraper\Facades\DecodoAsync;

$task = DecodoAsync::queueTaskWithBuilder(
    (new PayloadBuilder())
        ->url('https://example.com')
        ->headless('html')
        ->geo('United States')
        ->markdown()
);
```

### Queue a batch

Decodo enforces a 1-request-per-second rate limit on batch submissions. The package handles this for you automatically.

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

### v3 Batch Format
Decodo v3 supports passing an array of URLs in a single request. The package handles this via `queueBatch` or by passing URLs to `buildBatch()` in the `PayloadBuilder`:

```php
$payload = (new PayloadBuilder())
    ->geo('United States')
    ->buildBatch(['https://site1.com', 'https://site2.com']);
```

---

## Webhooks

The package includes a built-in webhook handler that automatically updates your local database records when Decodo tasks complete.

### Setup
1. Ensure `webhook.enabled` is `true` in your config.
2. Exempt the webhook path from CSRF protection in `app/Http/Middleware/VerifyCsrfToken.php` (Laravel 10) or your `bootstrap/app.php` (Laravel 11+):
   ```php
   'decodo/webhook/*'
   ```

### Automatic Injection
When `webhook.auto_inject_callback` is enabled, the package will automatically append the correct `callback_url` to every async request. You don't need to pass it manually unless you want to override it.

---

## Result Caching

To avoid redundant API calls and save credits, enable the `DecodoResultCache`. It caches results for "done" tasks (which are immutable) for up to 23 hours.

```php
'cache' => [
    'enabled' => true,
    'ttl' => 82800,
],
```

---

## Events

You can listen for the following events to trigger your own logic:

- `Rkdhatterwal\DecodoScraper\Events\DecodoTaskCompleted`
- `Rkdhatterwal\DecodoScraper\Events\DecodoTaskFaulted`
- `Rkdhatterwal\DecodoScraper\Events\DecodoTaskExpired`
- `Rkdhatterwal\DecodoScraper\Events\DecodoBatchCompleted`

```php
// Example: notify when a batch finishes
Event::listen(DecodoBatchCompleted::class, function ($event) {
    Log::info("Batch {$event->batch->id} is done!");
});
```

---

## Artisan Commands

- `php artisan decodo:status {taskId}` — Check the status of a specific task.
- `php artisan decodo:retry {taskId}` — Retry a faulted task.
- `php artisan decodo:prune` — Clean up old database records (scheduled daily by default).

---

## DTOs & Public Properties

All DTOs in this package use PHP 8.1+ `public readonly` properties for a better developer experience. You can access properties directly: `echo $result->content;`.

### ScrapeResult DTO

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

### TaskResponse DTO

Returned by `queueTask()` and `getTaskStatus()`.

```php
$task->id;          // Task ID for later retrieval
$task->status;      // "pending" | "done" | "faulted"
$task->isPending(); // bool
$task->isDone();    // bool
$task->isFaulted(); // bool
$task->toArray();
```

### BatchTaskResponse DTO

Returned by `queueBatch()`.

```php
$batch->id;         // Batch ID (v3)
$batch->tasks;      // Collection of TaskResponse DTOs
$batch->ids();       // Collection of task IDs
$batch->count();    // Total task count
$batch->toArray();
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

The package provides a powerful `DecodoFake` helper to mock API responses and assert that requests were sent.

```php
use Rkdhatterwal\DecodoScraper\Testing\DecodoFake;

$fake = DecodoFake::make()->swap();

// Stub a response
$fake->fakeScrape('<html>Hello World</html>');

// Act
$result = Decodo::scrape('https://example.com');

// Assert
$fake->assertScraped('https://example.com');
$this->assertEquals('<html>Hello World</html>', $result->content);
```

For async tasks:
```php
$fake->fakeTask('task-123');
DecodoAsync::queueTask('https://example.com');

$fake->assertTaskQueued('https://example.com');
```

For batches:
```php
$fake->fakeBatch(['task-1', 'task-2']);
DecodoAsync::queueBatch(['https://a.com', 'https://b.com']);

$fake->assertBatchQueued(2); // asserts batch with 2 URLs was queued
```

### Other Assertions

```php
$fake->assertNotScraped('https://example.com');
$fake->assertScrapeCount(5);
$fake->assertTaskNotQueued('https://example.com');
$fake->assertTaskQueuedCount(3);
$fake->assertBatchQueuedCount(1);
$fake->assertNothingSent();
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT
