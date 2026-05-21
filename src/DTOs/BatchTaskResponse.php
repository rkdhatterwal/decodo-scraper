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
    ) {}

    public static function fromArray(array $data): self
    {
        $tasks = collect($data)->map(
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
