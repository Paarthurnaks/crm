<?php

namespace App\Jobs;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TaskDeadlineExpirationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Task $task
    ) {}

    public function handle(): void
    {
        if ($this->task->deadline && Carbon::parse($this->task->deadline)->isPast()) {
            $this->task->update(['status' => TaskStatusEnum::EXPIRED->value]);
        }
    }
}
