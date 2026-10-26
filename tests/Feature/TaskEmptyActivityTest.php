<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
