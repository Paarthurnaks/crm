<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserField;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_field_attachment_requires_uploaded_files(): void
    {
        $field = UserField::create(['name' => 'File', 'entity' => 'deals', 'type' => 'file', 'code' => 'file']);

        $this->actingAs($this->createUser(), 'api')
            ->postJson('/api/user-field-values/attachments', [
                'user_field_id' => $field->id,
                'entity_id' => 1,
                'attachments' => ['not-a-file'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');
    }

    public function test_delete_attachment_returns_not_found_when_value_is_missing(): void
    {
        $field = UserField::create(['name' => 'File', 'entity' => 'deals', 'type' => 'file', 'code' => 'file']);

        $this->actingAs($this->createUser(), 'api')
            ->deleteJson('/api/user-field-values/attachments/999?user_field_id='.$field->id.'&entity_id=1')
            ->assertNotFound();
    }

    public function test_task_attachment_requires_uploaded_files(): void
    {
        $user = $this->createUser();
        $task = Task::withoutEvents(fn (): Task => Task::create(['title' => 'Example', 'creator_id' => $user->id]));

        $this->actingAs($user, 'api')
            ->postJson('/api/tasks/'.$task->id.'/attachments', ['attachments' => ['not-a-file']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachments.0');
    }

    public function test_task_attachment_delete_checks_ownership(): void
    {
        $user = $this->createUser();
        $task = Task::withoutEvents(fn (): Task => Task::create(['title' => 'Example', 'creator_id' => $user->id]));

        $this->actingAs($user, 'api')
            ->deleteJson('/api/tasks/'.$task->id.'/attachments/999')
            ->assertNotFound();
    }

    private function createUser(): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]));
    }
}
