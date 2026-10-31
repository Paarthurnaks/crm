<?php

namespace Tests\Feature;

use App\Enums\ChatTypeEnum;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\UnreadChatMessage;
use App\Models\User;
use App\Services\ChatMessageService;
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

    public function test_status_update_changes_only_selected_visible_messages_and_unread_rows(): void
    {
        $viewer = $this->createUser();
        $sender = $this->createUser();
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Status']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $viewer->id, 'is_creator' => false]);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $sender->id, 'is_creator' => true]);

        $selected = $this->createMessage($chat, $sender);
        $unselected = $this->createMessage($chat, $sender);
        $own = $this->createMessage($chat, $viewer);
        UnreadChatMessage::create(['chat_id' => $chat->id, 'chat_message_id' => $selected->id, 'user_id' => $viewer->id]);
        UnreadChatMessage::create(['chat_id' => $chat->id, 'chat_message_id' => $unselected->id, 'user_id' => $viewer->id]);

        $updated = (new ChatMessageService)->updateMessageStatus(
            ['message_ids' => [$selected->id, $own->id]], $chat->id, $viewer
        );

        $this->assertEqualsCanonicalizing([$selected->id, $own->id], $updated->pluck('id')->all());
        $this->assertSame('read', $selected->fresh()->status);
        $this->assertSame('sent', $unselected->fresh()->status);
        $this->assertSame('sent', $own->fresh()->status);
        $this->assertDatabaseMissing('unread_chat_messages', ['chat_message_id' => $selected->id, 'user_id' => $viewer->id]);
        $this->assertDatabaseHas('unread_chat_messages', ['chat_message_id' => $unselected->id, 'user_id' => $viewer->id]);
    }

    private function createMessage(Chat $chat, User $author): ChatMessage
    {
        return ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $author->id,
            'text' => 'Status message',
            'status' => 'sent',
        ]));
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
