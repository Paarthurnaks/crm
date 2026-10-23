<?php

namespace App\Observers;

use App\Enums\DefaultRolesEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Models\Permission;
use App\Services\ChatService;

class PermissionObserver
{
    public function __construct(
        private readonly ChatService $chatService
    )
    {
    }

    public function updated(Permission $permission): void
    {
        if($permission->isDirty('view') && $permission->entity === PermissionEntityEnum::PARTNERS_CHATS->value) {
            $permission->role->users->each(function ($user) {
                $this->chatService->setupChats($user);
            });
        }
    }

}
