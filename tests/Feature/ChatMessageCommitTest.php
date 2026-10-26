<?php

namespace Tests\Feature;

use App\Enums\ChatMessageTypeEnum;
use App\Enums\ChatTypeEnum;
use App\Events\Chat\GlobalChatsMessageSentBroadcast;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class ChatMessageCommitTest extends TestCase
{
    use RefreshDatabase;

    public function test_rolled_back_message_is_not_broadcast(): void
    {
        Event::fake([GlobalChatsMessageSentBroadcast::class]);
        $sender = $this->createBareUser('sender@example.test');
        $recipient = $this->createBareUser('recipient@example.test');
        $this->actingAs($sender, 'api');

        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Тестовый чат']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $sender->id, 'is_creator' => true]);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $recipient->id, 'is_creator' => false]);

        try {
            DB::transaction(function () use ($chat): void {
                (new ChatMessageService)->sendMessage(['text' => 'Сообщение'], $chat->id);
                throw new RuntimeException('rollback');
            });
            $this->fail('Ожидался откат транзакции.');
        } catch (RuntimeException) {
            $this->assertDatabaseMissing('chat_messages', ['chat_id' => $chat->id, 'text' => 'Сообщение']);
            Event::assertNotDispatched(GlobalChatsMessageSentBroadcast::class);
        }
    }

    public function test_global_message_is_sent_to_other_members_only(): void
    {
        Event::fake([GlobalChatsMessageSentBroadcast::class]);
        $sender = $this->createBareUser('broadcast-sender@example.test');
        $recipient = $this->createBareUser('broadcast-recipient@example.test');
        $this->actingAs($sender, 'api');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Тестовый чат']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $sender->id, 'is_creator' => true]);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $recipient->id, 'is_creator' => false]);
        $message = ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $sender->id,
            'text' => 'Тест',
            'status' => ChatMessageTypeEnum::DELIVERED->value,
        ]);

        (new ChatMessageService)->broadcastMessageSentToGlobal($message, $sender->id);

        Event::assertDispatchedTimes(GlobalChatsMessageSentBroadcast::class, 1);
        Event::assertDispatched(GlobalChatsMessageSentBroadcast::class, function ($event) use ($recipient): bool {
            return (string) $event->broadcastOn()[0]->name === 'private-global-chats.'.$recipient->id;
        });
    }

    private function createBareUser(string $email): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Иван',
            'surname' => 'Иванов',
            'email' => $email,
            'password' => 'unused',
        ]));
    }
}
