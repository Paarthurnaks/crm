<?php

namespace Tests\Feature;

use App\Enums\ChatTypeEnum;
use App\Http\Filters\ChatFilter;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatListPresentationQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_chat_list_serializes_without_per_chat_queries(): void
    {
        $viewer = User::withoutEvents(fn (): User => User::create([
            'name' => 'Иван',
            'surname' => 'Иванов',
            'email' => 'chat-list@example.test',
            'password' => 'unused',
        ]));
        $this->actingAs($viewer, 'api');

        foreach (range(1, 3) as $number) {
            $chat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Группа '.$number]);
            ChatMember::create(['chat_id' => $chat->id, 'user_id' => $viewer->id, 'is_creator' => true]);
        }

        $chats = app(ChatService::class)->getChatsList(['limit' => 10], new ChatFilter(Request::create('/', 'GET')));
        $this->assertCount(3, $chats);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = ChatResource::collection($chats)->response()->getData(true);

        $this->assertCount(3, $response['data']);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
