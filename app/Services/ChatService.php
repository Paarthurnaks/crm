<?php

namespace App\Services;

use App\Enums\ChatCategoryEnum;
use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Events\Chat\GlobalChatDeleteBroadcast;
use App\Events\Chat\GlobalChatUpdateBroadcast;
use App\Http\Filters\ChatFilter;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\ChatCategory;
use App\Models\ChatCategoryMapping;
use App\Models\ChatMember;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatService
{
    private const ERROR_MESSAGES = [
        'chatCreatorError' => 'Пользователь не является создателем чата',
        'singleChatAlreadyExists' => 'Чат между данными пользователями уже существует',
        'forbiddenAction' => 'Данное действие недоступно',
    ];

    public function __construct(
        private readonly ChatMemberService $chatMemberService
    ) {}

    public function getChatsList(array $data, ChatFilter $filter): LengthAwarePaginator
    {
        $limit = $data['limit'] ?? config('constants.paginator.limit');

        return Chat::query()
            ->filter($filter)
            ->when(! isset($data['category_id']), function ($query) {
                $query->whereDoesntHave('categories', function ($query) {
                    $query->whereHas('chatCategoryMappings', function ($query) {
                        $query->where('user_id', auth()->id());
                    });
                });
            })
            ->with('lastMessage')
            ->orderByRaw('(SELECT created_at FROM chat_messages WHERE chat_messages.chat_id = chats.id ORDER BY created_at DESC LIMIT 1) IS NULL') // Сортировка на основе наличия сообщений
            ->orderBy(DB::raw('(SELECT created_at FROM chat_messages WHERE chat_messages.chat_id = chats.id ORDER BY created_at DESC LIMIT 1)'), 'desc') // Сортировка по дате последнего сообщения
            ->paginate($limit);
    }

    /**
     * @throws Exception
     */
    public function create(array $data, ?int $userId = null): Chat
    {
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
        $chat->members->each(function ($member) use ($chat, $sendCurrentUser) {
            if ($member->user_id !== auth()->id() || $sendCurrentUser) {
                broadcast(
                    new GlobalChatUpdateBroadcast(
                        ChatResource::make($chat, $member->user_id),
                        $member->user_id
                    )
                );
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

    public function broadcastDeleteToGlobal(Chat $chat, bool $sendCurrenUser = false): void
    {
        $chat->members->each(function ($member) use ($chat, $sendCurrenUser) {
            if ($member->user_id !== auth()->id() || $sendCurrenUser) {
                broadcast(new GlobalChatDeleteBroadcast($chat->id, $member->user_id));
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
        $user->chatCategories()->detach();

        $systemPartnersCategoryId = ChatCategory::where('name', ChatCategoryEnum::SYSTEM_PARTNERS->value)->value('id');
        $systemPartnerClientsCategoryId = ChatCategory::where('name', ChatCategoryEnum::SYSTEM_PARTNER_CLIENTS->value)->value('id');

        $data = collect();

        if ($user->hasAccess(PermissionEntityEnum::PARTNERS_CHATS->value, 'view', PermissionTypeEnum::ALL->value)) {

            $partnerChats = $user->chats()
                ->withoutGlobalScopes()
                ->where('chats.type', ChatTypeEnum::PARTNER->value)
                ->pluck('chats.id')
                ->unique();

            $clientChats = $user->chats()
                ->withoutGlobalScopes()
                ->where('chats.type', ChatTypeEnum::CLIENT->value)
                ->pluck('chats.id')
                ->unique();

            $partnerChats->each(function ($chatId) use ($systemPartnersCategoryId, $user, &$data) {
                $data->push([
                    'chat_category_id' => $systemPartnersCategoryId,
                    'chat_id' => $chatId,
                    'user_id' => $user->id,
                ]);
            });

            $clientChats->each(function ($chatId) use ($systemPartnerClientsCategoryId, $user, &$data) {
                $data->push([
                    'chat_category_id' => $systemPartnerClientsCategoryId,
                    'chat_id' => $chatId,
                    'user_id' => $user->id,
                ]);
            });
        }

        ChatCategoryMapping::insert($data->toArray());
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

            try {
                $this->chatMemberService->create([
                    'user_ids' => $userIds,
                    'type' => ChatTypeEnum::CLIENT->value,
                ], $partnerChat, $user->id);

                $userIdsWithoutClientPartnerId = array_diff($userIds, [$clientPartner->id]);

                User::find($userIdsWithoutClientPartnerId)->each(function ($user) {
                    $this->setupSystemCategories($user);
                });
            } catch (\Throwable $e) {
                Log::info($e->getMessage());
            }
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
                try {
                    $this->chatMemberService->create([
                        'user_ids' => $userIds,
                        'type' => ChatTypeEnum::PARTNER->value,
                    ], $partnerChat, $user->id);

                    User::find($userIds)->each(function ($user) {
                        $this->setupSystemCategories($user);
                    });
                } catch (Exception $e) {
                    Log::info($e->getMessage());
                }
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

            try {
                $this->create($data, $user->id);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }
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

        try {
            $this->chatMemberService->create([
                'user_ids' => [$user->id],
                'type' => ChatTypeEnum::DEFAULT->value,
            ], $defaultChat);
        } catch (\Throwable $e) {
            Log::info($e->getMessage());
        }
    }

    private function createSingleUserChats(User $user): void
    {
        $chatIds = Chat::query()
            ->withoutGlobalScopes()
            ->where('type', ChatTypeEnum::systemTypes())
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
            try {
                $this->create([
                    'type' => ChatTypeEnum::SINGLE->value,
                    'user_ids' => [$anotherUserId],
                ], $user->id);
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }
        }
    }
}
