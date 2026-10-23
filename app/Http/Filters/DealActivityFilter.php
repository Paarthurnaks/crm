<?php

namespace App\Http\Filters;

use App\Http\Filters\Abstract\QueryFilter;
use App\Models\Task;
use BeyondCode\Comments\Comment;

class DealActivityFilter extends QueryFilter
{
    /**
     * @param string $search
     * @return void
     */
    public function search(string $search): void
    {
        $this->builder->where(function ($query) use ($search) {
            $query->whereHasMorph('activityable', [Comment::class, Task::class], function ($query, $type) use ($search) {
                $query->where(function ($query) use ($search, $type) {
                    if ($type === Comment::class) {
                        $query->orWhere('comment', 'like', "%{$search}%");
                    } elseif ($type === Task::class) {
                        $query->orWhere('title', 'like', "%{$search}%");
                    }
                });
            });
        });
    }

    /**
     * @param int $author_id
     * @return void
     */
    public function author_id(int $author_id): void
    {
        $this->builder->whereHasMorph('activityable', [Comment::class, Task::class], function ($query, $type) use ($author_id) {
            if ($type === Comment::class) {
                $query->where('user_id', $author_id);
            } elseif ($type === Task::class) {
                $query->where('creator_id', $author_id);
            }
        });
    }

    /**
     * @param string $date_from
     * @return void
     */
    public function date_from(string $date_from): void
    {
        $this->builder->whereDate('created_at', '>=', $date_from);
    }

    /**
     * @param string $date_to
     * @return void
     */
    public function date_to(string $date_to): void
    {
        $this->builder->whereDate('created_at', '<=', $date_to);
    }
}
