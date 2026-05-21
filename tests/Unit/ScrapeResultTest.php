<?php

use Rkdhatterwal\DecodoScraper\DTOs\ScrapeResult;

describe('ScrapeResult DTO', function () {

    it('hydrates correctly from an API array', function () {
        $result = ScrapeResult::fromArray([
            'content'     => '<html><h1>Hello</h1></html>',
            'status_code' => 200,
            'url'         => 'https://example.com',
            'task_id'     => 'abc123',
            'created_at'  => '2025-01-01 10:00:00',
            'updated_at'  => '2025-01-01 10:00:05',
        ]);

        expect($result->content)->toBe('<html><h1>Hello</h1></html>')
            ->and($result->statusCode)->toBe(200)
            ->and($result->url)->toBe('https://example.com')
            ->and($result->taskId)->toBe('abc123');
    });

    it('reports success for 2xx status codes', function () {
        $result = ScrapeResult::fromArray(['status_code' => 200, 'content' => '', 'url' => '', 'task_id' => '', 'created_at' => '', 'updated_at' => '']);
        expect($result->isSuccessful())->toBeTrue();
    });

    it('reports failure for non-2xx status codes', function () {
        $result = ScrapeResult::fromArray(['status_code' => 404, 'content' => '', 'url' => '', 'task_id' => '', 'created_at' => '', 'updated_at' => '']);
        expect($result->isSuccessful())->toBeFalse();
    });

    it('handles missing fields gracefully', function () {
        $result = ScrapeResult::fromArray([]);

        expect($result->content)->toBe('')
            ->and($result->statusCode)->toBe(0);
    });

    it('serializes back to an array', function () {
        $data = [
            'content'     => '<p>Test</p>',
            'status_code' => 200,
            'url'         => 'https://example.com',
            'task_id'     => 'xyz',
            'created_at'  => '2025-01-01 00:00:00',
            'updated_at'  => '2025-01-01 00:00:01',
        ];

        expect(ScrapeResult::fromArray($data)->toArray())->toBe($data);
    });
});
