<?php

namespace Tests\Feature;

use App\Enums\TaskTypeEnum;
use App\Jobs\TaskDeadlineExpirationJob;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskEmptyActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_without_comments_or_events_has_empty_activity(): void
    {
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User', 'email' => 'task-activity@example.test',
            'password' => 'password',
        ]));
        $task = Task::withoutEvents(fn (): Task => Task::create(['title' => 'Example', 'creator_id' => $user->id]));

        $this->assertSame([], app(TaskService::class)->getActivity($task, []));
    }

    public function test_task_without_deadline_does_not_schedule_expiration(): void
    {
        Queue::fake();
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User', 'email' => 'task-no-deadline@example.test',
            'password' => 'password',
        ]));

        app(TaskService::class)->create(['title' => 'Without deadline'], $user);

        Queue::assertNotPushed(TaskDeadlineExpirationJob::class);
    }

    public function test_future_deadline_schedules_expiration_at_the_deadline(): void
    {
        Queue::fake();
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User', 'email' => 'task-deadline@example.test',
            'password' => 'password',
        ]));
        $deadline = now()->addHour()->startOfSecond();

        Task::create([
            'title' => 'Future deadline',
            'creator_id' => $user->id,
            'type' => TaskTypeEnum::STANDARD->value,
            'deadline' => $deadline,
        ]);

        Queue::assertPushed(TaskDeadlineExpirationJob::class, function (TaskDeadlineExpirationJob $job) use ($deadline): bool {
            return $job->delay instanceof CarbonInterface && $job->delay->equalTo($deadline);
        });
    }

    public function test_all_task_types_can_be_created_without_http_actor(): void
    {
        Queue::fake();
        $actor = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User',
            'email' => 'task-types@example.test', 'password' => 'unused',
        ]));
        $service = app(TaskService::class);

        foreach (TaskTypeEnum::cases() as $type) {
            $task = $service->create([
                'title' => $type->value,
                'type' => $type->value,
            ], $actor);

            $this->assertSame($type->value, $task->type);
            $this->assertSame($actor->id, $task->creator_id);
        }
    }
}
