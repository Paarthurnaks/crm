<?php

namespace Tests\Unit;

use App\Enums\ChatTypeEnum;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\UnreadChatMessage;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class ChatPresentationTest extends TestCase
{
    public function test_group_delete_permission_and_unread_count_use_loaded_members_for_recipient(): void
    {
        $chat = Mockery::mock(Chat::class)->makePartial();
        $chat->type = ChatTypeEnum::GROUP->value;
        $chat->name = 'Группа';
        $chat->shouldReceive('getDisplayAvatar')->andReturn('/avatar.png');
        $chat->setRelation('members', new Collection([
            new ChatMember(['user_id' => 10, 'is_creator' => true]),
            new ChatMember(['user_id' => 20, 'is_creator' => false]),
        ]));

        $message = new ChatMessage(['text' => 'Сообщение']);
        $unread = new UnreadChatMessage(['user_id' => 20]);
        $unread->created_at = now();
        $unread->setRelation('message', $message);
        $chat->setRelation('unreadMessages', new Collection([$unread]));

        $creator = ChatResource::prepareForUser($chat, 10);
        $recipient = ChatResource::prepareForUser($chat, 20);

        $this->assertTrue($creator['can_delete']);
        $this->assertSame(0, $creator['unread_messages_count']);
        $this->assertFalse($recipient['can_delete']);
        $this->assertSame(1, $recipient['unread_messages_count']);
        $this->assertSame($message, $recipient['first_unread_message']);
    }
}
