<?php

namespace App\Http\Filters;

use App\Enums\TaskTypeEnum;
use App\Http\Filters\Abstract\QueryFilter;

class TaskFilter extends QueryFilter
{
    /**
     * @return void
     */
    public function search(string $search)
    {
        $this->builder->where('title', 'like', "%{$search}%");
    }

    public function title(string $title): void
    {
        $this->builder->where('title', 'like', "%{$title}%");
    }

    public function type(string $type): void
    {
        $realType = TaskTypeEnum::tryFrom($type);

        if (! is_null($realType)) {
            $this->builder->where('type', $realType);
        }
    }

    public function description(string $desc): void
    {
        $this->builder->where('description', 'like', "%{$desc}%");
    }

    public function status(string $status): void
    {
        $this->builder->where('status', $status);
    }

    public function deal(int $dealId): void
    {
        $this->builder->where('deal_id', $dealId);
    }

    public function creator(int $creatorId): void
    {
        $this->builder->where('creator_id', $creatorId);
    }

    public function deadline_from(string $date): void
    {
        $this->builder->where('deadline', '>=', $date);
    }

    public function deadline_to(string $date): void
    {
        $this->builder->where('deadline', '<=', $date);
    }

    public function executor_id(int $userId): void
    {
        $this->builder->whereHas('executors', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        });
    }

    public function created_at_from(string $fromDate): void
    {
        $this->builder->where('created_at', '>=', $fromDate);
    }

    public function created_at_to(string $toDate): void
    {
        $this->builder->where('created_at', '<=', $toDate);
    }

    public function updated_at_from(string $fromDate): void
    {
        $this->builder->where('updated_at', '>=', $fromDate);
    }

    public function updated_at_to(string $toDate): void
    {
        $this->builder->where('updated_at', '<=', $toDate);
    }
}
