<?php

namespace Tests\Feature;

use App\Enums\ChatTypeEnum;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessageStatusAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_member_cannot_mark_messages_read(): void
    {
        $user = $this->createUser();
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Private']);
        $message = ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'text' => 'Message',
            'status' => 'sent',
        ]));

        $this->actingAs($user, 'api')
            ->putJson("/api/chats/{$chat->id}/messages/status", ['message_ids' => [$message->id]])
            ->assertForbidden();
    }

    public function test_member_cannot_change_status_of_message_from_another_chat(): void
    {
        $user = $this->createUser();
        $ownChat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Own']);
        $otherChat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Other']);
        ChatMember::create(['chat_id' => $ownChat->id, 'user_id' => $user->id, 'is_creator' => false]);
        $otherMessage = ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::create([
            'chat_id' => $otherChat->id,
            'user_id' => $user->id,
            'text' => 'Other message',
            'status' => 'sent',
        ]));

        $this->actingAs($user, 'api')
            ->putJson("/api/chats/{$ownChat->id}/messages/status", ['message_ids' => [$otherMessage->id]])
            ->assertOk();

        $this->assertSame('sent', $otherMessage->fresh()->status);
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
