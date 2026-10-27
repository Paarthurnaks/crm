<?php

namespace Tests\Feature;

use App\Enums\ChatMessageTypeEnum;
use App\Enums\ChatTypeEnum;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatMediaDownloadAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_member_cannot_download_chat_message_attachment(): void
    {
        Storage::fake('public');
        $owner = $this->createUser('chat-file-owner@example.test');
        $outsider = $this->createUser('chat-file-outsider@example.test');
        $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Private']);
        ChatMember::create(['chat_id' => $chat->id, 'user_id' => $owner->id, 'is_creator' => true]);
        $message = ChatMessage::withoutEvents(fn (): ChatMessage => ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $owner->id,
            'status' => ChatMessageTypeEnum::DELIVERED->value,
            'text' => 'Attachment',
        ]));
        $media = $message->addMediaFromString('secret')
            ->usingFileName('secret.txt')
            ->toMediaCollection();

        $this->actingAs($outsider, 'api')
            ->getJson('/api/download/'.$media->id)
            ->assertForbidden();
    }

    private function createUser(string $email): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User',
            'email' => $email, 'password' => 'unused',
        ]));
    }
}
