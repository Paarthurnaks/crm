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

            if ($systemPartnersCategoryId === null || $systemPartnerClientsCategoryId === null) {
                Log::warning('chat.categories.missing', ['user_id' => $user->id]);
            }

            $partnerChats->each(function ($chatId) use ($systemPartnersCategoryId, $user, &$data) {
                if ($systemPartnersCategoryId === null) {
                    return;
                }

                $data->push([
                    'chat_category_id' => $systemPartnersCategoryId,
                    'chat_id' => $chatId,
                    'user_id' => $user->id,
                ]);
            });

            $clientChats->each(function ($chatId) use ($systemPartnerClientsCategoryId, $user, &$data) {
                if ($systemPartnerClientsCategoryId === null) {
                    return;
                }

                $data->push([
                    'chat_category_id' => $systemPartnerClientsCategoryId,
                    'chat_id' => $chatId,
                    'user_id' => $user->id,
                ]);
            });
        }

        if ($data->isNotEmpty()) {
            ChatCategoryMapping::insert($data->toArray());
        }

        Log::debug('chat.categories.initialized', ['user_id' => $user->id, 'mapping_count' => $data->count()]);
    }
}
