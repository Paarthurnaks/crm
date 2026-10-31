<?php

namespace Tests\Feature;

use App\Enums\ChatTypeEnum;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ForwardingChatMessage;
use App\Models\User;
use App\Services\ChatMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatMessageForwardTest extends TestCase
{
    use RefreshDatabase;

    public function test_forwarding_preserves_source_order_origin_and_media_for_each_destination(): void
    {
        Storage::fake('public');
        $actor = $this->createUser();
        $sourceChat = $this->createChat($actor);
        $firstTarget = $this->createChat($actor);
        $secondTarget = $this->createChat($actor);
        $original = $this->createMessage($sourceChat, $actor, 'Original');
        $original->addMediaFromString('attachment')->usingFileName('note.txt')->toMediaCollection();
        $alreadyForwarded = $this->createMessage($sourceChat, $actor, 'Forwarded');
        ForwardingChatMessage::create([
            'original_message_id' => $original->id,
            'forwarded_message_id' => $alreadyForwarded->id,
        ]);

        $result = (new ChatMessageService)->forwardMessage([
            'chat_ids' => [$firstTarget->id, $secondTarget->id],
            'message_ids' => [$original->id, $alreadyForwarded->id],
        ], $actor);

        $this->assertSame([$firstTarget->id, $firstTarget->id, $secondTarget->id, $secondTarget->id], $result->pluck('chat_id')->all());
        $this->assertSame(['Original', 'Forwarded', 'Original', 'Forwarded'], $result->pluck('text')->all());
        foreach ($result as $message) {
            $this->assertTrue((bool) $message->is_forwarded);
            $this->assertSame($original->id, $message->forward->original_message_id);
        }
        $this->assertCount(1, $result[0]->getMedia());
        $this->assertCount(1, $result[2]->getMedia());
    }

    public function test_forwarding_rolls_back_when_actor_is_not_member_of_a_destination(): void
    {
        $actor = $this->createUser();
        $sourceChat = $this->createChat($actor);
        $allowedTarget = $this->createChat($actor);
        $forbiddenTarget = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Forbidden']);
        $source = $this->createMessage($sourceChat, $actor, 'Source');

        try {
            (new ChatMessageService)->forwardMessage([
                'chat_ids' => [$allowedTarget->id, $forbiddenTarget->id],
                'message_ids' => [$source->id],
            ], $actor);
            $this->fail('Ожидался отказ при пересылке в чужой чат.');
        } catch (\Exception $exception) {
            $this->assertSame('Пользователь не является участником чата', $exception->getMessage());
        }

        $this->assertDatabaseMissing('chat_messages', ['chat_id' => $allowedTarget->id, 'text' => 'Source']);
        $this->assertDatabaseMissing('chat_messages', ['chat_id' => $forbiddenTarget->id, 'text' => 'Source']);
    }

    private function createUser(): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'unused',
        ]));
    }

    private function createChat(User $actor): Chat
    {
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Forward target']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $actor->id, 'is_creator' => true]);

        return $chat;
    }

    private function createMessage(Chat $chat, User $actor, string $text): ChatMessage
    {
        return ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $actor->id,
            'text' => $text,
            'status' => 'sent',
        ]));
    }
}
