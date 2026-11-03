<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_read_and_delete_are_scoped_to_authenticated_user(): void
    {
        $actor = $this->user();
        $other = $this->user();
        $ownId = $this->notify($actor, 'Own update');
        $secondId = $this->notify($actor, 'Second update');
        $otherId = $this->notify($other, 'Other update');

        $this->actingAs($actor, 'api')->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.notifications.meta.total', 2)
            ->assertJsonPath('data.total_unread_notifications', 2);

        $this->postJson('/api/notifications/read', [
            'notification_ids' => [$ownId, $otherId],
        ])->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $ownId]);
        $this->assertNotNull($actor->notifications()->findOrFail($ownId)->read_at);
        $this->assertNull($other->notifications()->findOrFail($otherId)->read_at);

        $this->deleteJson('/api/notifications/'.$otherId)->assertNotFound();
        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->assertNotNull($actor->notifications()->findOrFail($secondId)->read_at);
        $this->deleteJson('/api/notifications/'.$ownId)->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $ownId]);
        $this->assertDatabaseHas('notifications', ['id' => $otherId]);
    }

    public function test_notification_read_rejects_nonexistent_id(): void
    {
        $this->actingAs($this->user(), 'api')->postJson('/api/notifications/read', [
            'notification_ids' => [(string) Str::uuid()],
        ])->assertUnprocessable()->assertJsonValidationErrors('notification_ids.0');
    }

    private function notify(User $user, string $message): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id, 'type' => 'Example', 'data' => ['html' => $message],
        ]);

        return $id;
    }

    private function user(): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Notification', 'surname' => 'Tester',
            'email' => fake()->unique()->safeEmail(), 'password' => 'password',
        ]));
    }
}
