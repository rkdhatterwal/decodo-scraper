<?php

namespace Rkdhatterwal\DecodoScraper\DTOs;

class ScrapeResult
{
    public function __construct(
        public readonly string $content,
        public readonly int    $statusCode,
        public readonly string $url,
        public readonly string $taskId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Hydrate a ScrapeResult from a raw API result array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            content:   $data['content']    ?? '',
            statusCode: $data['status_code'] ?? 0,
            url:       $data['url']         ?? '',
            taskId:    $data['task_id']     ?? '',
            createdAt: $data['created_at']  ?? '',
            updatedAt: $data['updated_at']  ?? '',
        );
    }

    /**
     * Returns true when the upstream page returned a successful status code.
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function toArray(): array
    {
        return [
            'content'     => $this->content,
            'status_code' => $this->statusCode,
            'url'         => $this->url,
            'task_id'     => $this->taskId,
            'created_at'  => $this->createdAt,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
