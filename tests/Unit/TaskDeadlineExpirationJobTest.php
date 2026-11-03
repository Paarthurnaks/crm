<?php

namespace Tests\Unit;

use App\Enums\TaskStatusEnum;
use App\Jobs\TaskDeadlineExpirationJob;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskDeadlineExpirationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_task_is_marked_expired_when_job_runs(): void
    {
        Queue::fake();
        $task = $this->task(now()->subMinute()->format('Y-m-d H:i:s'));

        (new TaskDeadlineExpirationJob($task))->handle();

        $this->assertSame(TaskStatusEnum::EXPIRED->value, $task->fresh()->status);
    }

    public function test_future_deadline_does_not_change_status(): void
    {
        Queue::fake();
        $task = $this->task(now()->addHour()->format('Y-m-d H:i:s'));
        $before = $task->status;

        (new TaskDeadlineExpirationJob($task))->handle();

        $this->assertSame($before, $task->fresh()->status);
    }

    private function task(string $deadline): Task
    {
        $actor = User::withoutEvents(fn (): User => User::create([
            'name' => 'Deadline', 'surname' => 'Tester',
            'email' => fake()->unique()->safeEmail(), 'password' => 'password',
        ]));

        return Task::withoutEvents(fn (): Task => Task::create([
            'title' => 'Deadline task', 'creator_id' => $actor->id,
            'status' => TaskStatusEnum::NEW->value, 'deadline' => $deadline,
        ]));
    }
}
