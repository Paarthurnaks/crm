<?php

namespace App\Observers;

use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Models\Chat;
use App\Models\User;
use App\Services\ChatService;

class UserObserver
{
    public function __construct(private readonly ChatService $chatService) {}

    public function deleting(User $user): void
    {
        $chats = Chat::where(function ($query) use ($user) {
            $query->where('type', ChatTypeEnum::SINGLE->value)
                ->when($user->hasRole(DefaultRolesEnum::PARTNER->value) || $user->hasRole(DefaultRolesEnum::CLIENT->value), function ($query) {
                    $query->orWhere('type', ChatTypeEnum::CLIENT->value);
                })
                ->when($user->hasRole(DefaultRolesEnum::PARTNER->value), function ($query) {
                    $query->orWhere('type', ChatTypeEnum::PARTNER->value);
                });
        })
            ->whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();

        foreach ($chats as $chat) {
            $this->chatService->broadcastDeleteToGlobal($chat, true);
        }

        $clientIds = $user->clients()->pluck('users.id')->toArray();
        User::whereIn('id', $clientIds)->delete();
    }
}
