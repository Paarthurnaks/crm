<?php

namespace App\Observers;

use App\Models\TaskEvent;
use App\Notifications\TaskEventNotification;

class TaskEventObserver
{
    /**
     * Handle the TaskEvent "created" event.
     */
    public function created(TaskEvent $taskEvent): void
    {
        if($taskEvent->task->creator_id !== auth()->id()) {
            $taskEvent->task->creator->notify(new TaskEventNotification($taskEvent));
        }
    }
}
