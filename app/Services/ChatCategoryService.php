<?php

namespace App\Services;

use App\Enums\ChatCategoryEnum;
use App\Enums\ChatTypeEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Models\ChatCategory;
use App\Models\ChatCategoryMapping;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ChatCategoryService
{
    public function setupSystemCategories(User $user): void
    {
        $user->chatCategories()->detach();

        $categoryIds = ChatCategory::whereIn('name', [
            ChatCategoryEnum::SYSTEM_PARTNERS->value,
            ChatCategoryEnum::SYSTEM_PARTNER_CLIENTS->value,
        ])->pluck('id', 'name');
        $data = [];

        if ($user->hasAccess(PermissionEntityEnum::PARTNERS_CHATS->value, 'view', PermissionTypeEnum::ALL->value)) {
            $chatsByType = $user->chats()
                ->withoutGlobalScopes()
                ->whereIn('chats.type', [ChatTypeEnum::PARTNER->value, ChatTypeEnum::CLIENT->value])
                ->get(['chats.id', 'chats.type'])
                ->unique('id');

            if (! $categoryIds->has(ChatCategoryEnum::SYSTEM_PARTNERS->value) ||
                ! $categoryIds->has(ChatCategoryEnum::SYSTEM_PARTNER_CLIENTS->value)) {
                Log::warning('chat.categories.missing', ['user_id' => $user->id]);
            }

            foreach ($chatsByType as $chat) {
                $categoryName = $chat->type === ChatTypeEnum::PARTNER->value
                    ? ChatCategoryEnum::SYSTEM_PARTNERS->value
                    : ChatCategoryEnum::SYSTEM_PARTNER_CLIENTS->value;
                if (! $categoryIds->has($categoryName)) {
                    continue;
                }

                $data[] = [
                    'chat_category_id' => $categoryIds->get($categoryName),
                    'chat_id' => $chat->id,
                    'user_id' => $user->id,
                ];
            }
        }

        if ($data !== []) {
            ChatCategoryMapping::insert($data);
        }

        Log::debug('chat.categories.initialized', ['user_id' => $user->id, 'mapping_count' => count($data)]);
    }
}
