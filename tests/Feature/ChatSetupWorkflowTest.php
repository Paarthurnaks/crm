<?php

namespace Tests\Feature;

use App\Enums\ChatTypeEnum;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
