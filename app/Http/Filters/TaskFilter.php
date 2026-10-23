<?php

namespace App\Http\Filters;

use App\Enums\TaskTypeEnum;
use App\Http\Filters\Abstract\QueryFilter;
use Illuminate\Support\Carbon;

class TaskFilter extends QueryFilter
{

    /**
     * @param string $search
     * @return void
     */
    public function search(string $search)
    {
        $this->builder->where("title", "like", "%{$search}%");
    }

    /**
     * @param string $title
     * @return void
     */
    public function title(string $title): void
    {
        $this->builder->where("title", 'like', "%{$title}%");
    }

    /**
     * @param string $type
     * @return void
     */
    public function type(string $type): void
    {
        $realType = TaskTypeEnum::tryFrom($type);

        if(!is_null($realType)) {
            $this->builder->where("type", $realType);
        }
    }

    /**
     * @param string $desc
     * @return void
     */
    public function description(string $desc): void
    {
        $this->builder->where("description", 'like',"%{$desc}%");
    }

    /**
     * @param string $status
     * @return void
     */
    public function status(string $status): void
    {
        $this->builder->where("status", $status);
    }

    /**
     * @param int $dealId
     * @return void
     */
    public function deal(int $dealId): void
    {
        $this->builder->where("deal_id", $dealId);
    }

    /**
     * @param int $creatorId
     * @return void
     */
    public function creator(int $creatorId): void
    {
        $this->builder->where("creator_id", $creatorId);
    }

    /**
     * @param string $date
     * @return void
     */
    public function deadline_from(string $date): void
    {
        $this->builder->where("deadline", ">=", $date);
    }

    /**
     * @param string $date
     * @return void
     */
    public function deadline_to(string $date): void
    {
        $this->builder->where("deadline", "<=", $date);
    }

    /**
     * @param int $userId
     * @return void
     */
    public function executor_id(int $userId): void
    {
        $this->builder->whereHas('executors', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        });
    }

    /**
     * @param string $fromDate
     * @return void
     *
     */
    public function created_at_from(string $fromDate): void
    {
        $this->builder->where("created_at", ">=", $fromDate);
    }

    /**
     * @param string $toDate
     * @return void
     *
     */
    public function created_at_to(string $toDate): void
    {
        $this->builder->where("created_at", "<=", $toDate);
    }

    /**
     * @param string $fromDate
     * @return void
     *
     */
    public function updated_at_from(string $fromDate): void
    {
        $this->builder->where("updated_at", ">=", $fromDate);
    }

    /**
     * @param string $toDate
     * @return void
     *
     */
    public function updated_at_to(string $toDate): void
    {
        $this->builder->where("updated_at", "<=", $toDate);
    }
}
