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
            DB::transaction(function () use ($chat, $sender): void {
                (new ChatMessageService)->sendMessage(['text' => 'Сообщение'], $chat->id, $sender);
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

    public function test_unread_recipients_come_from_message_sender_without_http_context(): void
    {
        $sender = $this->createBareUser('background-sender@example.test');
        $recipient = $this->createBareUser('background-recipient@example.test');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Background']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $sender->id, 'is_creator' => true]);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $recipient->id, 'is_creator' => false]);

        $message = ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $sender->id,
            'text' => 'Background message',
            'status' => ChatMessageTypeEnum::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('unread_chat_messages', [
            'chat_message_id' => $message->id,
            'user_id' => $recipient->id,
        ]);
        $this->assertDatabaseMissing('unread_chat_messages', [
            'chat_message_id' => $message->id,
            'user_id' => $sender->id,
        ]);
    }

    public function test_service_sends_message_with_explicit_actor_without_http_context(): void
    {
        Event::fake([GlobalChatsMessageSentBroadcast::class]);
        $sender = $this->createBareUser('service-sender@example.test');
        $recipient = $this->createBareUser('service-recipient@example.test');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Service']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $sender->id, 'is_creator' => true]);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $recipient->id, 'is_creator' => false]);

        $message = (new ChatMessageService)->sendMessage(['text' => 'Without request'], $chat->id, $sender);

        $this->assertSame($sender->id, $message->user_id);
        $this->assertDatabaseHas('unread_chat_messages', [
            'chat_message_id' => $message->id,
            'user_id' => $recipient->id,
        ]);
        Event::assertDispatchedTimes(GlobalChatsMessageSentBroadcast::class, 1);
    }

    public function test_message_pages_keep_upward_and_downward_order(): void
    {
        config()->set('chats.messages_limit', 2);
        $viewer = $this->createBareUser('page-viewer@example.test');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Page']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $viewer->id, 'is_creator' => true]);
        $ids = [];
        foreach (range(1, 7) as $number) {
            $ids[] = ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::create([
                'chat_id' => $chat->id,
                'user_id' => $viewer->id,
                'text' => "Page {$number}",
                'status' => ChatMessageTypeEnum::DELIVERED->value,
            ]))->id;
        }

        $page = (new ChatMessageService)->getMessagesList($chat->id, [
            'direction' => 'both',
            'last_message_id' => $ids[3],
        ], $viewer);

        $this->assertTrue($page['has_more_upward']);
        $this->assertTrue($page['has_more_downward']);
        $this->assertCount(5, $page['messages']);
    }

    public function test_hidden_message_is_excluded_for_explicit_viewer(): void
    {
        $viewer = $this->createBareUser('hidden-viewer@example.test');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Hidden']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $viewer->id, 'is_creator' => true]);
        $message = ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $viewer->id,
            'text' => 'Hidden message',
            'status' => ChatMessageTypeEnum::DELIVERED->value,
        ]));
        $service = new ChatMessageService;

        $service->hideMessage($message->id, $viewer);

        $page = $service->getMessagesList($chat->id, [], $viewer);
        $this->assertCount(0, $page['messages']);
    }

    public function test_message_pages_preserve_order_and_flags_at_limit_boundary(): void
    {
        config()->set('chats.messages_limit', 2);
        $viewer = $this->createBareUser('direction-viewer@example.test');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Directions']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $viewer->id, 'is_creator' => true]);

        $ids = [];
        foreach (range(1, 7) as $number) {
            $ids[] = ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::create([
                'chat_id' => $chat->id,
                'user_id' => $viewer->id,
                'text' => "Message {$number}",
                'status' => ChatMessageTypeEnum::DELIVERED->value,
            ]))->id;
        }

        $service = new ChatMessageService;
        $cases = [
            [[], [$ids[6], $ids[5]], true, false],
            [['direction' => 'upward', 'last_message_id' => $ids[3]], [$ids[2], $ids[1]], true, true],
            [['direction' => 'downward', 'last_message_id' => $ids[3]], [$ids[5], $ids[6]], true, true],
            [['direction' => 'both', 'last_message_id' => $ids[3]], [$ids[2], $ids[1], $ids[3], $ids[5], $ids[6]], true, true],
            [['direction' => 'upward', 'last_message_id' => $ids[1]], [$ids[0]], false, true],
        ];

        foreach ($cases as [$params, $expectedIds, $hasMoreUpward, $hasMoreDownward]) {
            $page = $service->getMessagesList($chat->id, $params, $viewer);

            $this->assertSame($expectedIds, $page['messages']->map(fn ($resource) => $resource->resource->id)->all());
            $this->assertSame($hasMoreUpward, $page['has_more_upward']);
            $this->assertSame($hasMoreDownward, $page['has_more_downward']);
        }
    }

    public function test_message_search_filters_neighbors_but_keeps_requested_anchor(): void
    {
        config()->set('chats.messages_limit', 2);
        $viewer = $this->createBareUser('search-viewer@example.test');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Search']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $viewer->id, 'is_creator' => true]);

        $ids = [];
        foreach (['match one', 'anchor', 'match two'] as $text) {
            $ids[] = ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::create([
                'chat_id' => $chat->id,
                'user_id' => $viewer->id,
                'text' => $text,
                'status' => ChatMessageTypeEnum::DELIVERED->value,
            ]))->id;
        }

        $page = (new ChatMessageService)->getMessagesList($chat->id, [
            'direction' => 'both',
            'last_message_id' => $ids[1],
            'search_term' => 'match',
        ], $viewer);

        $this->assertSame([$ids[0], $ids[1], $ids[2]], $page['messages']->map(fn ($resource) => $resource->resource->id)->all());
        $this->assertFalse($page['has_more_upward']);
        $this->assertFalse($page['has_more_downward']);
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
