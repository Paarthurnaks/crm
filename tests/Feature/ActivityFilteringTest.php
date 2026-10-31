<?php

namespace Tests\Feature;

use App\Http\Filters\DealActivityFilter;
use App\Http\Resources\TaskResource;
use App\Models\Deal;
use App\Models\EntityActivity;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\Task;
use App\Models\User;
use App\Services\EntityActivityService;
use App\Services\TaskService;
use BeyondCode\Comments\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ActivityFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_activity_type_is_excluded_from_page_but_total_stays_query_total(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user);
        $funnel = SalesFunnel::firstOrFail();
        $stage = SalesStage::where('sales_funnel_id', $funnel->id)->firstOrFail();
        $deal = Deal::withoutEvents(fn (): Deal => Deal::create([
            'title' => 'Activity',
            'sales_funnel_id' => $funnel->id,
            'sales_stage_id' => $stage->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'note' => '',
        ]));
        EntityActivity::create([
            'entity_type' => Deal::class, 'entity_id' => $deal->id,
            'activityable_type' => User::class, 'activityable_id' => $user->id,
        ]);
        EntityActivity::create([
            'entity_type' => Deal::class, 'entity_id' => $deal->id,
            'activityable_type' => Task::class, 'activityable_id' => $task->id,
        ]);

        $page = app(EntityActivityService::class)->getActivity(
            $deal,
            new DealActivityFilter(Request::create('/', 'GET')),
            [Task::class => ['type' => 'task', 'resource' => TaskResource::class]],
            ['limit' => 10]
        );

        $this->assertSame(2, $page->total());
        $this->assertCount(1, $page->items());
        $this->assertSame('task', array_values($page->items())[0]['type']);
    }

    public function test_date_from_filters_approved_task_comments(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user);
        $comment = new Comment;
        $comment->forceFill([
            'commentable_type' => Task::class,
            'commentable_id' => $task->id,
            'user_id' => $user->id,
            'comment' => 'Old comment',
            'is_approved' => true,
            'created_at' => '2024-01-01 12:00:00',
        ]);
        $comment->save();

        $this->assertCount(0, app(TaskService::class)->getCommentsActivity($task, [
            'date_from' => '2024-02-01',
        ]));
    }

    private function createUser(): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User',
            'email' => fake()->unique()->safeEmail(), 'password' => 'unused',
        ]));
    }

    private function createTask(User $user): Task
    {
        return Task::withoutEvents(fn (): Task => Task::create([
            'title' => 'Activity', 'creator_id' => $user->id,
        ]));
    }
}
