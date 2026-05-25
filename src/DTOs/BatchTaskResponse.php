<?php

namespace Rkdhatterwal\DecodoScraper\DTOs;

use Illuminate\Support\Collection;

class BatchTaskResponse
{
    /**
     * @param  Collection<int, TaskResponse>  $tasks
     */
    public function __construct(
        public readonly Collection $tasks,
        public readonly ?string $id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        // Check if it's the new v3 batch response format
        if (isset($data['queries']) && is_array($data['queries'])) {
            $tasks = collect($data['queries'])->map(
                fn (array $task) => TaskResponse::fromArray($task)
            );

            return new self(
                tasks: $tasks,
                id: (string) ($data['id'] ?? null)
            );
        }

        // Fallback for legacy format (array of tasks)
        $items = isset($data[0]) ? $data : [$data];

        $tasks = collect($items)->map(
            fn (array $task) => TaskResponse::fromArray($task)
        );

        return new self(tasks: $tasks);
    }

    /**
     * Return only the task IDs — useful for later result polling.
     *
     * @return Collection<int, string>
     */
    public function ids(): Collection
    {
        return $this->tasks->pluck('id');
    }

    public function count(): int
    {
        return $this->tasks->count();
    }

    public function toArray(): array
    {
        return $this->tasks->map->toArray()->all();
    }
}
