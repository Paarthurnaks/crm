<?php

namespace Tests\Feature;

use App\Enums\ChatTypeEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatCreationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_chat_type_cannot_be_created_through_api(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'api')
            ->postJson('/api/chats', [
                'type' => ChatTypeEnum::DEFAULT->value,
                'user_ids' => [$user->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_chat_list_keeps_pagination_metadata(): void
    {
        $this->actingAs($this->createUser(), 'api')
            ->getJson('/api/chats')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
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
