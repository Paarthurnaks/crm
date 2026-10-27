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

        $chats = app(ChatService::class)->getChatsList(
            ['limit' => 10],
            new ChatFilter(Request::create('/', 'GET')),
            $viewer
        );
        $this->assertCount(3, $chats);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = ChatResource::collection($chats)->response()->getData(true);

        $this->assertCount(3, $response['data']);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_search_never_returns_another_users_chat_without_http_context(): void
    {
        $viewer = User::withoutEvents(fn (): User => User::create([
            'name' => 'Viewer', 'surname' => 'User',
            'email' => 'chat-search-viewer@example.test', 'password' => 'unused',
        ]));
        $other = User::withoutEvents(fn (): User => User::create([
            'name' => 'Other', 'surname' => 'User',
            'email' => 'chat-search-other@example.test', 'password' => 'unused',
        ]));
        $ownChat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Project room']);
        $otherChat = Chat::create(['type' => ChatTypeEnum::GROUP->value, 'name' => 'Project private']);
        ChatMember::create(['chat_id' => $ownChat->id, 'user_id' => $viewer->id, 'is_creator' => true]);
        ChatMember::create(['chat_id' => $otherChat->id, 'user_id' => $other->id, 'is_creator' => true]);
        $filter = new ChatFilter(Request::create('/', 'GET', ['search_term' => 'project']));

        $chats = app(ChatService::class)->getChatsList(['limit' => 10], $filter, $viewer);

        $this->assertSame([$ownChat->id], $chats->getCollection()->pluck('id')->all());
    }
}
