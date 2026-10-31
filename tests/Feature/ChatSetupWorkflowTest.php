<?php

namespace Tests\Feature;

use App\Enums\ChatTypeEnum;
use App\Events\Chat\GlobalChatDeleteBroadcast;
use App\Events\Chat\GlobalChatUpdateBroadcast;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\User;
use App\Services\ChatService;
use App\Services\ChatCategoryService;
use App\Services\ChatMemberService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ChatSetupWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_chat_setup_is_repeatable_and_restores_membership(): void
    {
        $user = $this->createBareUser('setup@example.test');
        $service = app(ChatService::class);

        $service->setupChats($user);
        $defaultChat = Chat::withoutGlobalScopes()->where('type', ChatTypeEnum::DEFAULT->value)->firstOrFail();
        $member = ChatMember::where('chat_id', $defaultChat->id)->where('user_id', $user->id)->firstOrFail();
        $member->delete();

        $service->setupChats($user);
        $service->setupChats($user);

        $this->assertSame(1, ChatMember::withTrashed()->where('chat_id', $defaultChat->id)->where('user_id', $user->id)->count());
        $this->assertFalse($member->fresh()->trashed());
        $this->assertSame(1, Chat::withoutGlobalScopes()->where('type', ChatTypeEnum::DEFAULT->value)->count());
    }

    public function test_manual_chat_creation_supports_single_group_and_partner_types(): void
    {
        $creator = $this->createBareUser('creator@example.test');
        $recipient = $this->createBareUser('recipient@example.test');
        $service = app(ChatService::class);

        foreach ([ChatTypeEnum::SINGLE, ChatTypeEnum::GROUP, ChatTypeEnum::PARTNER] as $type) {
            $chat = $service->create([
                'type' => $type->value,
                'name' => 'Тестовый чат',
                'user_ids' => [$recipient->id],
            ], $creator->id);

            $this->assertSame($type->value, $chat->type);
            $this->assertSame(2, ChatMember::where('chat_id', $chat->id)->count());
        }
    }

    public function test_duplicate_single_chat_is_rejected_without_an_http_user(): void
    {
        $creator = $this->createBareUser('single-creator@example.test');
        $recipient = $this->createBareUser('single-recipient@example.test');
        $service = app(ChatService::class);
        $data = [
            'type' => ChatTypeEnum::SINGLE->value,
            'user_ids' => [$recipient->id],
        ];
        $service->create($data, $creator->id);

        try {
            $service->create($data, $creator->id);
            $this->fail('Ожидался отказ повторного создания чата.');
        } catch (Exception $exception) {
            $this->assertSame('Чат между данными пользователями уже существует', $exception->getMessage());
            $this->assertSame(1, Chat::withoutGlobalScopes()->where('type', ChatTypeEnum::SINGLE->value)->count());
        }
    }

    public function test_failed_category_setup_rolls_back_default_chat_membership(): void
    {
        $user = $this->createBareUser('setup-rollback@example.test');
        $categories = Mockery::mock(ChatCategoryService::class);
        $categories->shouldReceive('setupSystemCategories')->once()->andThrow(new RuntimeException('setup failed'));
        $service = new ChatService(app(ChatMemberService::class), $categories);

        try {
            $service->setupChats($user);
            $this->fail('Ожидался откат инициализации чата.');
        } catch (RuntimeException) {
            $this->assertSame(0, ChatMember::where('user_id', $user->id)->count());
            $this->assertSame(0, Chat::withoutGlobalScopes()->where('type', ChatTypeEnum::DEFAULT->value)->count());
        }
    }

    public function test_chat_update_and_delete_broadcast_to_other_member_only(): void
    {
        Event::fake([GlobalChatUpdateBroadcast::class, GlobalChatDeleteBroadcast::class]);
        $actor = $this->createBareUser('chat-actor@example.test');
        $recipient = $this->createBareUser('chat-recipient@example.test');
        $this->actingAs($actor, 'api');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Before']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $actor->id, 'is_creator' => true]);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $recipient->id, 'is_creator' => false]);
        $service = app(ChatService::class);

        $service->update($chat, ['name' => 'After']);
        $service->broadcastDeleteToGlobal($chat);

        Event::assertDispatchedTimes(GlobalChatUpdateBroadcast::class, 1);
        Event::assertDispatchedTimes(GlobalChatDeleteBroadcast::class, 1);
        Event::assertDispatched(GlobalChatUpdateBroadcast::class, fn ($event): bool =>
            $event->broadcastOn()[0]->name === 'private-global-chats.'.$recipient->id);
        Event::assertDispatched(GlobalChatDeleteBroadcast::class, fn ($event): bool =>
            $event->broadcastOn()[0]->name === 'private-global-chats.'.$recipient->id);
    }

    public function test_only_group_creator_can_delete_group_chat(): void
    {
        Event::fake([GlobalChatDeleteBroadcast::class]);
        $creator = $this->createBareUser('group-creator@example.test');
        $member = $this->createBareUser('group-member@example.test');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Group']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $creator->id, 'is_creator' => true]);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $member->id, 'is_creator' => false]);
        $service = app(ChatService::class);

        $this->actingAs($member, 'api');
        try {
            $service->delete($chat);
            $this->fail('Ожидался отказ для участника чата.');
        } catch (Exception $exception) {
            $this->assertSame('Пользователь не является создателем чата', $exception->getMessage());
        }

        $this->actingAs($creator, 'api');
        $service->delete($chat);
        $this->assertDatabaseMissing('chats', ['id' => $chat->id]);
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
