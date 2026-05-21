<?php

use Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult;
use Rkdhatterwal\DecodoScraper\DTOs\TaskResponse;
use Rkdhatterwal\DecodoScraper\DTOs\BatchTaskResponse;
use Rkdhatterwal\DecodoScraper\Testing\DecodoFake;

describe('DecodoFake — sync client', function () {

    it('records scrape calls and returns default stub', function () {
        $fake = DecodoFake::make()->swap();

        $result = app('decodo')->scrape('https://example.com');

        expect($result)->toBeInstanceOf(ScrapeResult::class);
        $fake->assertScraped('https://example.com');
        $fake->assertScrapeCount(1);
    });

    it('returns the stubbed content from fakeScrape()', function () {
        $fake = DecodoFake::make();
        $fake->fakeScrape('<h1>Stubbed</h1>', 200);
        $fake->swap();

        $result = app('decodo')->scrape('https://example.com');

        expect($result->content)->toBe('<h1>Stubbed</h1>')
            ->and($result->statusCode)->toBe(200);
    });

    it('assertNotScraped passes when URL was not scraped', function () {
        $fake = DecodoFake::make()->swap();
        app('decodo')->scrape('https://a.com');

        $fake->assertNotScraped('https://b.com'); // should not throw
    });

    it('assertScrapeCount fails when count is wrong', function () {
        $fake = DecodoFake::make()->swap();
        app('decodo')->scrape('https://a.com');

        expect(fn () => $fake->assertScrapeCount(2))
            ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
    });

    it('records scrapeMany calls', function () {
        $fake = DecodoFake::make()->swap();
        app('decodo')->scrapeMany(['https://a.com', 'https://b.com']);

        $fake->assertScrapeCount(1); // one call, multiple URLs
    });

    it('assertNothingSent passes when no calls were made', function () {
        $fake = DecodoFake::make()->swap();
        $fake->assertNothingSent(); // should not throw
    });

    it('assertNothingSent fails when a scrape was sent', function () {
        $fake = DecodoFake::make()->swap();
        app('decodo')->scrape('https://example.com');

        expect(fn () => $fake->assertNothingSent())
            ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
    });
});

describe('DecodoFake — async client', function () {

    it('records queueTask calls and returns default stub', function () {
        $fake = DecodoFake::make()->swap();

        $response = app('decodo.async')->queueTask('https://example.com');

        expect($response)->toBeInstanceOf(TaskResponse::class)
            ->and($response->status)->toBe('pending');

        $fake->assertTaskQueued('https://example.com');
        $fake->assertTaskQueuedCount(1);
    });

    it('returns the stubbed task from fakeTask()', function () {
        $fake = DecodoFake::make();
        $fake->fakeTask('my-task-001', 'pending', 'https://example.com');
        $fake->swap();

        $response = app('decodo.async')->queueTask('https://example.com');

        expect($response->id)->toBe('my-task-001');
    });

    it('records queueBatch calls', function () {
        $fake = DecodoFake::make()->swap();

        app('decodo.async')->queueBatch(['https://a.com', 'https://b.com']);

        $fake->assertBatchQueued(2);
        $fake->assertBatchQueuedCount(1);
    });

    it('returns stubbed batch from fakeBatch()', function () {
        $fake = DecodoFake::make();
        $fake->fakeBatch(['task-a', 'task-b']);
        $fake->swap();

        $response = app('decodo.async')->queueBatch(['https://a.com', 'https://b.com']);

        expect($response)->toBeInstanceOf(BatchTaskResponse::class)
            ->and($response->count())->toBe(2)
            ->and($response->ids()->first())->toBe('task-a');
    });

    it('returns stubbed task status from fakeTaskStatus()', function () {
        $fake = DecodoFake::make();
        $fake->fakeTaskStatus('task-001', 'done');
        $fake->swap();

        $status = app('decodo.async')->getTaskStatus('task-001');

        expect($status->isDone())->toBeTrue();
    });

    it('returns stubbed results from fakeTaskResults()', function () {
        $fake = DecodoFake::make();
        $fake->fakeTaskResults('task-001', '<p>Stubbed result</p>', 200);
        $fake->swap();

        $results = app('decodo.async')->getTaskResults('task-001');

        expect($results->first()->content)->toBe('<p>Stubbed result</p>');
    });

    it('assertTaskNotQueued passes when URL was not queued', function () {
        $fake = DecodoFake::make()->swap();
        app('decodo.async')->queueTask('https://a.com');

        $fake->assertTaskNotQueued('https://b.com'); // should not throw
    });

    it('failOnUnstubbed throws when no stub is configured', function () {
        $fake = DecodoFake::make()->failOnUnstubbed()->swap();

        expect(fn () => app('decodo')->scrape('https://example.com'))
            ->toThrow(\Rkdhatterwal\DecodoScraper\Exceptions\DecodoException::class);
    });

    it('exposes recorded calls via accessors', function () {
        $fake = DecodoFake::make()->swap();
        app('decodo.async')->queueTask('https://example.com');
        app('decodo.async')->getTaskStatus('task-001');
        app('decodo.async')->getTaskResults('task-001');

        expect($fake->recordedTasks())->toHaveCount(1)
            ->and($fake->recordedStatusChecks())->toHaveCount(1)
            ->and($fake->recordedResultFetches())->toHaveCount(1);
    });
});
