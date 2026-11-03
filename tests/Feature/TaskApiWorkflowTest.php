<?php

namespace Tests\Feature;

use App\Enums\PermissionTypeEnum;
use App\Enums\TaskStatusEnum;
use App\Jobs\TaskDeadlineExpirationJob;
use App\Models\Comment;
use App\Models\Deal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Queue::fake();
        $role = Role::create(['title' => 'Task manager']);
        Permission::create([
            'role_id' => $role->id, 'entity' => 'tasks',
            'view' => PermissionTypeEnum::ALL->value,
            'create' => PermissionTypeEnum::ALL->value,
            'update' => PermissionTypeEnum::ALL->value,
            'delete' => PermissionTypeEnum::ALL->value,
        ]);
        $this->actor = $this->user($role);
        $this->actingAs($this->actor, 'api');
    }

    public function test_task_crud_preserves_deal_people_deadline_and_activity(): void
    {
        $participant = $this->user();
        $deal = $this->deal();
        $deadline = now()->addDay()->startOfSecond()->format('Y-m-d H:i:s');

        $created = $this->postJson('/api/tasks', [
            'title' => 'Review deal', 'description' => 'Check details',
            'deal_id' => $deal->id, 'deadline' => $deadline,
            'executors' => [$participant->id], 'observers' => [$this->actor->id],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Review deal')
            ->assertJsonPath('data.deal.id', $deal->id)
            ->assertJsonPath('data.executors.0.id', $participant->id);
        $taskId = $created->json('data.id');
        $this->assertDatabaseHas('tasks', ['id' => $taskId, 'deal_id' => $deal->id]);
        Queue::assertPushed(TaskDeadlineExpirationJob::class);

        $this->getJson('/api/tasks/'.$taskId)->assertOk()->assertJsonPath('data.id', $taskId);
        $this->getJson('/api/tasks?title=Review')->assertOk()->assertJsonPath('data.total', 1);

        $this->putJson('/api/tasks/'.$taskId, [
            'title' => 'Reviewed deal', 'executors' => [$this->actor->id],
        ])->assertOk()->assertJsonPath('data.title', 'Reviewed deal');
        $this->assertDatabaseHas('tasks', ['id' => $taskId, 'title' => 'Reviewed deal']);
        $this->assertSame([$this->actor->id], Task::findOrFail($taskId)->executors->pluck('id')->all());

        $commentId = $this->postJson('/api/tasks/'.$taskId.'/comments', ['comment' => 'Done'])
            ->assertOk()->assertJsonPath('data.comment', 'Done')->json('data.id');
        $this->getJson('/api/tasks/'.$taskId.'/comments')->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $commentId);
        $this->getJson('/api/tasks/'.$taskId.'/activity')->assertOk();

        $this->deleteJson('/api/comments/'.$commentId)->assertOk();
        $this->assertDatabaseMissing('comments', ['id' => $commentId]);
        $this->deleteJson('/api/tasks/'.$taskId)->assertOk();
        $this->assertDatabaseMissing('tasks', ['id' => $taskId]);
    }

    public function test_task_validation_and_past_deadline_behavior(): void
    {
        $this->postJson('/api/tasks', ['title' => 'Missing description', 'deal_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors(['description', 'deal_id']);
        $this->assertSame(0, Task::count());

        $id = $this->postJson('/api/tasks', [
            'title' => 'Late task', 'description' => 'Already late',
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'),
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('tasks', ['id' => $id, 'status' => TaskStatusEnum::EXPIRED->value]);
        Queue::assertNotPushed(TaskDeadlineExpirationJob::class);

        $this->putJson('/api/tasks/'.$id, ['deadline' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('deadline');
        $this->assertDatabaseHas('tasks', ['id' => $id, 'title' => 'Late task']);
    }

    public function test_only_comment_author_can_edit_or_delete_it(): void
    {
        $task = $this->postJson('/api/tasks', [
            'title' => 'Comment task', 'description' => 'Discussion',
        ])->assertCreated()->json('data.id');
        $commentId = $this->postJson('/api/tasks/'.$task.'/comments', ['comment' => 'Original'])
            ->assertOk()->json('data.id');
        $other = $this->user();

        $this->actingAs($other, 'api')->putJson('/api/comments/'.$commentId, [
            'comment' => 'Tampered',
        ])->assertNotFound();
        $this->deleteJson('/api/comments/'.$commentId)->assertNotFound();
        $this->assertDatabaseHas('comments', ['id' => $commentId, 'comment' => 'Original']);

        $this->actingAs($this->actor, 'api')->putJson('/api/comments/'.$commentId, [
            'comment' => 'Edited',
        ])->assertOk();
        $this->assertSame('Edited', Comment::findOrFail($commentId)->comment);
    }

    public function test_task_attachment_can_be_added_and_removed(): void
    {
        Storage::fake('public');
        $taskId = $this->postJson('/api/tasks', [
            'title' => 'Attachment task', 'description' => 'File attached',
        ])->assertCreated()->json('data.id');

        $response = $this->postJson('/api/tasks/'.$taskId.'/attachments', [
            'attachments' => [UploadedFile::fake()->create('proof.txt', 1, 'text/plain')],
        ])->assertOk()->assertJsonCount(1, 'data.attachments');
        $attachmentId = $response->json('data.attachments.0.id');
        $this->assertDatabaseHas('media', ['id' => $attachmentId, 'model_id' => $taskId]);

        $this->deleteJson('/api/tasks/'.$taskId.'/attachments/'.$attachmentId)->assertOk();
        $this->assertDatabaseMissing('media', ['id' => $attachmentId]);
        $this->deleteJson('/api/tasks/'.$taskId.'/attachments/'.$attachmentId)->assertNotFound();
    }

    private function user(?Role $role = null): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Task', 'surname' => 'Tester', 'email' => fake()->unique()->safeEmail(),
            'password' => 'password', 'role_id' => $role?->id,
        ]));
    }

    private function deal(): Deal
    {
        $funnel = SalesFunnel::firstOrFail();
        $stage = SalesStage::where('sales_funnel_id', $funnel->id)->firstOrFail();

        return Deal::create([
            'title' => 'Task deal', 'sales_funnel_id' => $funnel->id,
            'sales_stage_id' => $stage->id, 'creator_id' => $this->actor->id,
            'responsible_id' => $this->actor->id, 'note' => '',
        ]);
    }
}
