<?php

namespace App\Services;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Events\Chat\GlobalChatCreateBroadcast;
use App\Events\Chat\GlobalChatDeleteBroadcast;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatMemberService
{
    public function __construct(private readonly ChatCategoryService $chatCategoryService) {}

    private const ERROR_MESSAGES = [
        'chatCreatorError' => 'Пользователь не является создателем чата',
        'userAlreadyInChat' => 'Пользователь уже состоит в чате',
    ];

    public function getMembers(Chat $chat): Collection
    {
        $query = ChatMember::where('chat_id', $chat->id);

        if (in_array($chat->type, [ChatTypeEnum::PARTNER->value, ChatTypeEnum::CLIENT->value]) &&
            auth()->user()->hasRole(DefaultRolesEnum::havePersonalAccount())
        ) {
            $query->where('chat_id', $chat->id)->whereHas('user.role', function ($query) {
                $query->whereIn('original_title', DefaultRolesEnum::havePersonalAccount());
            });
        }

        return $query->get();
    }

    /**
     * @throws Exception
     */
    public function create(array $data, Chat $chat, ?int $creatorId = null): Collection
    {
        $user = User::find($creatorId) ?? auth()->user();

        $chatId = $chat->id;
        if ($user &&
            $chat &&
            $chat->type === ChatTypeEnum::GROUP->value &&
            $chat->members()->exists() &&
            ! $user->isCreatorOfChat($chat->id)
        ) {
            throw new Exception(self::ERROR_MESSAGES['chatCreatorError']);
        }

        $membersData = [];

        // удаляем из массива creatorId
        $data['user_ids'] = array_diff($data['user_ids'], [$creatorId]);

        foreach ($data['user_ids'] as $index => $userId) {
            $chatMember = ChatMember::withTrashed()->where('user_id', $userId)->where('chat_id', $chatId)->first();

            if ($chatMember) {
                if ($chatMember->trashed()) {
                    $chatMember->restore();
                } else {
                    unset($data['user_ids'][$index]);
                }

                continue;
            }

            $membersData[] = [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'is_creator' => false,
            ];
        }

        if ($creatorId !== null) {
            $chatMember = ChatMember::withTrashed()->where('user_id', $creatorId)->where('chat_id', $chatId)->first();

            if ($chatMember) {
                if ($chatMember->trashed()) {
                    $chatMember->restore();
                }
            } else {
                $membersData[] = [
                    'chat_id' => $chatId,
                    'user_id' => $creatorId,
                    'is_creator' => $data['type'] === ChatTypeEnum::GROUP->value || $data['type'] === ChatTypeEnum::PARTNER->value,
                ];
            }
        }

        ChatMember::insert($membersData);

        $this->broadcastAddingToChatToGlobal($data['user_ids'], $chat);

        $members = ChatMember::where('chat_id', $chatId)
            ->whereIn('user_id', $data['user_ids'])
            ->get();

        Log::debug('Chat members added', ['chat_id' => $chatId, 'member_count' => $members->count()]);

        return $members;
    }

    public function broadcastAddingToChatToGlobal(array $user_ids, Chat $chat): void
    {

        if (in_array($chat->type, ChatTypeEnum::personalTypes())) {
            User::find($user_ids)->each(function ($user) {
                $this->chatCategoryService->setupSystemCategories($user);
            });
        }
        $chat = $chat->fresh(['lastMessage' => ['chat', 'user', 'forward'], 'category', 'unreadMessages.message.user', 'members.user.role', 'messages.forward']);

        DB::afterCommit(function () use ($user_ids, $chat): void {
            foreach ($user_ids as $userId) {
                try {
                    broadcast(new GlobalChatCreateBroadcast(ChatResource::make($chat, $userId), $userId));
                } catch (\Throwable $exception) {
                    Log::error('chat.member_create_broadcast.failed', ['chat_id' => $chat->id, 'user_id' => $userId, 'exception' => $exception::class]);
                }
            }
        });
    }

    /**
     * @throws Exception
     */
    public function delete(ChatMember $chatMember): void
    {
        if (! auth()->user()->isCreatorOfChat($chatMember->chat_id)) {
            throw new Exception(self::ERROR_MESSAGES['chatCreatorError']);
        }

        DB::afterCommit(function () use ($chatMember): void {
            try {
                broadcast(new GlobalChatDeleteBroadcast($chatMember->chat_id, $chatMember->user_id));
            } catch (\Throwable $exception) {
                Log::error('chat.member_delete_broadcast.failed', [
                    'chat_id' => $chatMember->chat_id,
                    'user_id' => $chatMember->user_id,
                    'exception' => $exception::class,
                ]);
            }
        });

        $chatMember->forceDelete();
    }
}
