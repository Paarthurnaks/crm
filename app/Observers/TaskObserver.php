<?php

namespace App\Observers;

use App\Enums\TaskEventTypeEnum;
use App\Enums\TaskStatusEnum;
use App\Enums\TaskTypeEnum;
use App\Jobs\TaskDeadlineExpirationJob;
use App\Models\Deal;
use App\Models\EntityActivity;
use App\Models\Task;
use App\Models\TaskEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Lang;

class TaskObserver
{
    public function created(Task $task): void
    {
        $type = TaskEventTypeEnum::NEW_TASK->value;

        if ($task->type === TaskTypeEnum::DOCKET->value) {
            $type = TaskEventTypeEnum::NEW_DOCKET->value;
        }

        TaskEvent::create([
            'task_id' => $task->id,
            'initiator_id' => $task->creator_id,
            'type' => $type,
        ]);

        if ($task->deal_id) {
            EntityActivity::create([
                'entity_id' => $task->deal_id,
                'entity_type' => Deal::class,
                'activityable_type' => Task::class,
                'activityable_id' => $task->id,
            ]);
        }

        if ($task->deadline &&
            ($task->type === TaskTypeEnum::DOCKET->value || $task->type === TaskTypeEnum::STANDARD->value)) {
            $deadline = Carbon::parse($task->deadline);
            if ($deadline->isFuture()) {
                TaskDeadlineExpirationJob::dispatch($task)->delay($deadline)->afterCommit();
            }
        }
    }

    public function updating(Task $task): void
    {
        $values = Lang::get('tasks');
        foreach ($values as $key => $value) {
            if ($task->isDirty($key)) {
                $type = $key == 'status' ? TaskEventTypeEnum::CHANGE_STATUS->value : TaskEventTypeEnum::CHANGE_FIELD->value;
                TaskEvent::create([
                    'task_id' => $task->id,
                    'initiator_id' => auth()->id() ?? null,
                    'type' => $type,
                    'changed_field' => trans('tasks.'.$key),
                    'old_value' => $task->getOriginal($key),
                    'new_value' => $task->{$key},
                ]);
            }
        }

        if ($task->isDirty('deadline') && $task->deadline) {
            $deadline = Carbon::parse($task->deadline);

            if ($deadline->isFuture()) {
                $task->status = TaskStatusEnum::PENDING->value;

                TaskDeadlineExpirationJob::dispatch($task)->delay($deadline)->afterCommit();
            } elseif ($deadline->isPast()) {
                TaskDeadlineExpirationJob::dispatch($task)->afterCommit();
            }
        }
    }

    public function deleting(Task $task): void
    {
        $task->activities()->delete();
    }
}
