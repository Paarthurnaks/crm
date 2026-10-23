<?php

namespace App\Services;

use App\Models\TaskEvent;
use Illuminate\Database\Eloquent\Collection;

class TaskEventService
{
    public function getTaskEvents(int $taskId): Collection
    {
        return TaskEvent::where("task_id", $taskId)->get();
    }
}
