<?php

namespace App\Services;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Events\Chat\GlobalChatDeleteBroadcast;
use App\Events\Chat\GlobalChatUpdateBroadcast;
use App\Http\Filters\ChatFilter;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\PartnerClient;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ChatService
{
    private const ERROR_MESSAGES = [
        'chatCreatorError' => 'Пользователь не является создателем чата',
        'singleChatAlreadyExists' => 'Чат между данными пользователями уже существует',
        'forbiddenAction' => 'Данное действие недоступно',
    ];

    public function __construct(
        private readonly ChatMemberService $chatMemberService,
        private readonly ChatCategoryService $chatCategoryService,
    ) {}

    public function getChatsList(array $data, ChatFilter $filter): LengthAwarePaginator
    {
        $limit = $data['limit'] ?? config('constants.paginator.limit');

        $chats = Chat::query()
            ->filter($filter)
            ->when(! isset($data['category_id']), function ($query) {
                $query->whereDoesntHave('categories', function ($query) {
                    $query->whereHas('chatCategoryMappings', function ($query) {
                        $query->where('user_id', auth()->id());
                    });
                });
            })
            ->with([
                'lastMessage.chat',
                'lastMessage.user',
                'lastMessage.user.role',
                'lastMessage.user.media',
                'lastMessage.forward',
                'lastMessage.media',
                'category',
                'members.user.role',
                'members.user.media',
                'unreadMessages.message.user',
                'unreadMessages.message.user.role',
                'unreadMessages.message.user.media',
                'unreadMessages.message.chat',
                'unreadMessages.message.media',
                'media',
            ])
            ->orderByRaw('(SELECT created_at FROM chat_messages WHERE chat_messages.chat_id = chats.id ORDER BY created_at DESC LIMIT 1) IS NULL') // Сортировка на основе наличия сообщений
            ->orderBy(DB::raw('(SELECT created_at FROM chat_messages WHERE chat_messages.chat_id = chats.id ORDER BY created_at DESC LIMIT 1)'), 'desc') // Сортировка по дате последнего сообщения
            ->paginate($limit);

        $viewerId = auth()->id();
        $authors = $chats->getCollection()
            ->flatMap(function (Chat $chat) {
                return collect([$chat->lastMessage?->user])
                    ->concat($chat->unreadMessages->map(fn ($unread) => $unread->message?->user));
            })
            ->filter()
            ->unique('id');

        (new EloquentCollection($authors->all()))->load(['partnerDeals', 'highLevelAdmin']);
        $partnerClientIds = PartnerClient::where('partner_id', $viewerId)
            ->whereIn('client_id', $authors->pluck('id'))
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $chats->getCollection()->each(function (Chat $chat) use ($viewerId, $partnerClientIds): void {
            $chat->setAttribute('partner_client_ids', $partnerClientIds);
            $chat->setAttribute('chat_presentation', ChatResource::prepareForUser($chat, $viewerId));
        });

        Log::debug('chat.list.prepared', ['viewer_id' => $viewerId, 'chat_count' => $chats->count()]);

        return $chats;
    }

    /**
     * @throws Exception
     */
    public function create(array $data, ?int $userId = null): Chat
    {
        if (! in_array($data['type'] ?? null, [
            ChatTypeEnum::SINGLE->value,
            ChatTypeEnum::GROUP->value,
            ChatTypeEnum::PARTNER->value,
        ], true)) {
            Log::warning('Отклонено создание чата с неподдерживаемым типом');

            throw new InvalidArgumentException('Недопустимый тип чата для ручного создания');
        }

        Log::debug('chat.create.scenario_selected', ['type' => $data['type'], 'actor_id' => $userId ?? auth()->id()]);

        try {
            return DB::transaction(function () use ($data, $userId) {
                $userId = $userId ?? auth()->id();

                if ($data['type'] === ChatTypeEnum::SINGLE->value) {
                    // проверка существует ли чат между двумя пользователями
                    $isChatExists = Chat::with('members')
                        ->where('type', ChatTypeEnum::SINGLE->value)
                        ->whereHas('members', function ($query) use ($userId) {
                            $query->where('user_id', $userId);
                        })
                        ->whereHas('members', function ($query) use ($data) {
                            $query->where('user_id', $data['user_ids'][0]);
                        })
                        ->exists();

                    if ($isChatExists) {
                        throw new Exception(self::ERROR_MESSAGES['singleChatAlreadyExists']);
                    }

                    $chat = Chat::create([
                        'type' => $data['type'],
                    ]);
                } elseif ($data['type'] === ChatTypeEnum::GROUP->value) {
                    $chat = Chat::create($data);

                    if (isset($data['avatar'])) {
                        $chat->addMedia($data['avatar'])->toMediaCollection();
                    }
                } elseif ($data['type'] === ChatTypeEnum::PARTNER->value) {
                    $chat = Chat::create($data);
                }

                if (is_null($chat)) {
                    throw new Exception(self::ERROR_MESSAGES['chatCreatorError']);
                }

                $this->chatMemberService->create($data, $chat, $userId);

                return $chat;
            });
        } catch (\Throwable $exception) {
            Log::error('chat.create.failed', ['type' => $data['type'], 'actor_id' => $userId ?? auth()->id(), 'exception' => $exception::class]);
            throw $exception;
        }
    }

    /**
     * @throws Exception
     */
    public function update(Chat $chat, array $data): Chat
    {
        if (! auth()->user()->isCreatorOfChat($chat->id)) {
            throw new Exception(self::ERROR_MESSAGES['chatCreatorError']);
        }

        if (isset($data['avatar'])) {
            $chat->addMedia($data['avatar'])->toMediaCollection();
        }

        $chat->update($data);

        $this->broadcastUpdateToGlobal($chat, false);

        return $chat;
    }

    private function broadcastUpdateToGlobal(Chat $chat, bool $sendCurrentUser = true): void
    {
        $actorId = auth()->id();
        $recipientIds = $chat->members->pluck('user_id')
            ->filter(fn ($id) => $sendCurrentUser || $id !== $actorId)
            ->all();

        DB::afterCommit(function () use ($chat, $recipientIds): void {
            foreach ($recipientIds as $recipientId) {
                try {
                    broadcast(new GlobalChatUpdateBroadcast(ChatResource::make($chat, $recipientId), $recipientId));
                } catch (\Throwable $exception) {
                    Log::error('chat.update_broadcast.failed', ['chat_id' => $chat->id, 'user_id' => $recipientId, 'exception' => $exception::class]);
                }
            }
        });
    }

    /**
     * @throws Exception
     */
    public function delete(Chat $chat): void
    {
        switch ($chat->type) {
            case ChatTypeEnum::GROUP->value:
                if (! auth()->user()->isCreatorOfChat($chat)) {
                    throw new Exception(self::ERROR_MESSAGES['chatCreatorError']);
                }
                break;

            case ChatTypeEnum::SINGLE->value:
                if ($chat->members()->count() !== 1) {
                    throw new Exception(self::ERROR_MESSAGES['forbiddenAction']);
                }
                break;

            case ChatTypeEnum::PARTNER->value:
                $partnerExists = $chat->members()->whereHas('user.role', function ($query) {
                    return $query->where('original_title', DefaultRolesEnum::PARTNER->value);
                })->exists();

                if ($partnerExists) {
                    throw new Exception(self::ERROR_MESSAGES['forbiddenAction']);
                }
                break;

            case ChatTypeEnum::CLIENT->value:
                $clientExists = $chat->members()->whereHas('user.role', function ($query) {
                    return $query->where('original_title', DefaultRolesEnum::CLIENT->value);
                })->exists();

                $partnerExists = $chat->members()->whereHas('user.role', function ($query) {
                    return $query->where('original_title', DefaultRolesEnum::PARTNER->value);
                })->exists();

                if ($clientExists && $partnerExists) {
                    throw new Exception(self::ERROR_MESSAGES['forbiddenAction']);
                }
                break;
        }

        $this->broadcastDeleteToGlobal($chat);

        $chat->delete();
    }

    public function broadcastDeleteToGlobal(Chat $chat, bool $sendCurrentUser = false): void
    {
        $actorId = auth()->id();
        $recipientIds = $chat->members->pluck('user_id')
            ->filter(fn ($id) => $sendCurrentUser || $id !== $actorId)
            ->all();

        DB::afterCommit(function () use ($chat, $recipientIds): void {
            foreach ($recipientIds as $recipientId) {
                try {
                    broadcast(new GlobalChatDeleteBroadcast($chat->id, $recipientId));
                } catch (\Throwable $exception) {
                    Log::error('chat.delete_broadcast.failed', ['chat_id' => $chat->id, 'user_id' => $recipientId, 'exception' => $exception::class]);
                }
            }
        });
    }

    private function getUsersWithPartnerChatAccess(): array
    {
        return User::whereHas('role.permissions', function ($query) {
            $query->where('entity', PermissionEntityEnum::PARTNERS_CHATS->value)
                ->where('view', PermissionTypeEnum::ALL->value);
        })
            ->pluck('id')
            ->toArray();
    }

    public function setupChats(User $user): void
    {
        Log::info('chat.setup.started', ['user_id' => $user->id]);

        try {
            DB::transaction(function () use ($user): void {
                if (! $user->hasRole(DefaultRolesEnum::havePersonalAccount())) {
                    $this->deleteChatMembersExcept($user, ChatTypeEnum::systemTypes());
                    $this->setupDefaultChat($user);
                    $this->createSingleUserChats($user);

                    if ($user->hasAccess(PermissionEntityEnum::PARTNERS_CHATS->value, 'view', PermissionTypeEnum::ALL->value)) {
                        $this->setupSystemPartnerChats();
                    }

                    $this->setupSystemCategories($user);
                } elseif ($user->hasRole(DefaultRolesEnum::PARTNER->value)) {
                    $user->chatCategories()->detach();
                    $this->deleteChatMembersExcept($user, []);
                    $this->setupPartnerChat($user);
                } elseif ($user->hasRole(DefaultRolesEnum::CLIENT->value)) {
                    $user->chatCategories()->detach();
                    $this->deleteChatMembersExcept($user, [ChatTypeEnum::CLIENT->value]);
                    $this->createChatWithPartner($user);
                }
            });

            Log::info('chat.setup.completed', ['user_id' => $user->id]);
        } catch (\Throwable $exception) {
            Log::error('chat.setup.failed', ['user_id' => $user->id, 'exception' => $exception::class]);
            throw $exception;
        }
    }

    private function setupSystemPartnerChats(): void
    {
        User::whereHas('role', function ($query) {
            $query->where('original_title', DefaultRolesEnum::PARTNER->value);
        })
            ->get()
            ->each(function ($user) {
                $this->setupPartnerChat($user);
            });
    }

    public function setupSystemCategories(User $user): void
    {
        $this->chatCategoryService->setupSystemCategories($user);
    }

    private function deleteChatMembersExcept(User $user, array $excludedTypes): void
    {
        $chatMemberIds = ChatMember::whereHas('chat', function ($query) use ($excludedTypes) {
            $query->withoutGlobalScopes()
                ->whereNotIn('type', $excludedTypes);
        })
            ->where('user_id', $user->id)
            ->pluck('id')
            ->toArray();

        ChatMember::whereIn('id', $chatMemberIds)->delete();

        Chat::whereHas('members', function ($query) use ($chatMemberIds) {
            $query->withTrashed()->whereIn('id', $chatMemberIds);
        })
            ->get()
            ->each(function ($chat) {
                $this->broadcastUpdateToGlobal($chat);
            });
    }

    private function createChatWithPartner(User $user): void
    {
        $clientPartner = $user->partner;

        if ($clientPartner) {
            $partnerChat = Chat::withoutGlobalScopes()
                ->where('type', ChatTypeEnum::CLIENT->value)
                ->whereHas('members', function ($query) use ($user) {
                    $query->withTrashed()->where('user_id', $user->id);
                })->first();

            if (! $partnerChat) {
                $partnerChat = Chat::create([
                    'type' => ChatTypeEnum::CLIENT->value,
                ]);
            }

            $userIds = $this->getUsersWithPartnerChatAccess();
            $userIds[] = $clientPartner->id;

            $this->chatMemberService->create([
                'user_ids' => $userIds,
                'type' => ChatTypeEnum::CLIENT->value,
            ], $partnerChat, $user->id);

            $userIdsWithoutClientPartnerId = array_diff($userIds, [$clientPartner->id]);

            User::find($userIdsWithoutClientPartnerId)->each(function ($recipient) {
                $this->setupSystemCategories($recipient);
            });
        }
    }

    private function setupPartnerChat(User $user): void
    {
        $partnerChat = $user->chats()
            ->withoutGlobalScopes()
            ->whereHas('members', function ($query) use ($user) {
                $query->withTrashed()->where('is_creator', true)->where('user_id', $user->id);
            })
            ->where('type', ChatTypeEnum::PARTNER->value)
            ->first();

        if ($partnerChat) {
            $userIds = $this->getUsersWithPartnerChatAccess();

            if ($userIds) {
                $this->chatMemberService->create([
                    'user_ids' => $userIds,
                    'type' => ChatTypeEnum::PARTNER->value,
                ], $partnerChat, $user->id);

                User::find($userIds)->each(function ($recipient) {
                    $this->setupSystemCategories($recipient);
                });
            }

        } else {
            $userIds = User::whereHas('role.permissions', function ($query) {
                $query->where('entity', PermissionEntityEnum::PARTNERS_CHATS->value)
                    ->where('view', PermissionTypeEnum::ALL->value);
            })
                ->pluck('id')
                ->toArray();

            $data = [
                'type' => ChatTypeEnum::PARTNER->value,
                'user_ids' => $userIds,
            ];

            $this->create($data, $user->id);
        }
    }

    private function setupDefaultChat(User $user): void
    {
        $defaultChat = Chat::withoutGlobalScopes()->firstOrCreate([
            'type' => ChatTypeEnum::DEFAULT->value,
        ]);

        $defaultChatMember = ChatMember::onlyTrashed()
            ->where('chat_id', $defaultChat->id)
            ->where('user_id', $user->id)
            ->first();

        if ($defaultChatMember) {
            $defaultChatMember->restore();

            return;
        }

        $this->chatMemberService->create([
            'user_ids' => [$user->id],
            'type' => ChatTypeEnum::DEFAULT->value,
        ], $defaultChat);
    }

    private function createSingleUserChats(User $user): void
    {
        $chatIds = Chat::query()
            ->withoutGlobalScopes()
            ->whereIn('type', ChatTypeEnum::systemTypes())
            ->pluck('id');

        ChatMember::withTrashed()
            ->whereIn('chat_id', $chatIds)
            ->where('user_id', $user->id)
            ->restore();

        $userId = $user->id;

        $usersWithChats = ChatMember::whereIn('chat_id', function ($query) use ($userId) {
            $query->select('chat_id')
                ->from('chat_members')
                ->join('chats', 'chat_members.chat_id', '=', 'chats.id')
                ->where('chat_members.user_id', $userId)
                ->where('chats.type', 'single');
        })->pluck('user_id')->toArray();

        $usersWithoutSingleIds = User::where(function ($query) {
            $query->whereHas('role', function ($query) {
                $query->whereNotIn('original_title', DefaultRolesEnum::havePersonalAccount())
                    ->orWhereNull('original_title');
            })
                ->orWhereNull('role_id');
        })->whereNotIn('id', $usersWithChats)
            ->where('id', '!=', $userId)
            ->pluck('id');

        foreach ($usersWithoutSingleIds as $anotherUserId) {
            $this->create([
                'type' => ChatTypeEnum::SINGLE->value,
                'user_ids' => [$anotherUserId],
            ], $user->id);
        }
    }
}
