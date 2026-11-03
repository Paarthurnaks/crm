<?php

namespace Tests\Feature;

use App\Enums\PermissionTypeEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['title' => 'Test administrator']);
        Permission::create([
            'role_id' => $role->id,
            'entity' => 'users',
            'view' => PermissionTypeEnum::ALL->value,
            'create' => PermissionTypeEnum::ALL->value,
            'update' => PermissionTypeEnum::ALL->value,
            'delete' => PermissionTypeEnum::ALL->value,
        ]);

        $this->actor = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'Administrator',
            'email' => 'user-admin@example.test',
            'password' => 'password',
            'role_id' => $role->id,
        ]));

        Notification::fake();
        $this->actingAs($this->actor, 'api');
    }

    public function test_user_can_be_created_listed_updated_and_deleted(): void
    {
        $created = $this->postJson('/api/users', [
            'name' => 'New',
            'surname' => 'User',
            'email' => 'new-user@example.test',
            'active' => true,
        ])->assertCreated();

        $user = User::where('email', 'new-user@example.test')->firstOrFail();
        $this->assertSame($user->id, $created->json('data.id'));
        $this->assertFalse((bool) $user->active);

        $this->getJson('/api/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'new-user@example.test');

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->putJson('/api/users/'.$user->id, ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated');
        $this->assertSame('Updated', $user->fresh()->name);

        $this->deleteJson('/api/users/'.$user->id)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_store_and_update_reject_invalid_input_without_mutating_users(): void
    {
        $this->postJson('/api/users', [
            'name' => 'Invalid',
            'surname' => 'User',
            'email' => $this->actor->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('users', ['name' => 'Invalid']);

        $this->putJson('/api/users/'.$this->actor->id, [
            'name' => 'Changed',
            'password' => 'forbidden',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertSame('Test', $this->actor->fresh()->name);
    }

    public function test_missing_user_returns_not_found(): void
    {
        $this->getJson('/api/users/999999')->assertNotFound();
    }
}
