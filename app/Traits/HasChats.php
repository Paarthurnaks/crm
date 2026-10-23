<?php

namespace App\Traits;

use App\Models\Chat;
use App\Models\ChatCategory;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

trait HasChats
{
    public function isCreatorOfChat($chatId): bool
    {
        return ChatMember::where('user_id', auth()->id())->where('chat_id', $chatId)->where('is_creator', true)->exists();
    }

    public function isMemberOfChat($chatId): bool
    {
        return ChatMember::where('user_id', auth()->id())->where('chat_id', $chatId)->exists();
    }

    public function isAuthorOfChatMessage($messageId): bool
    {
        return ChatMessage::where('id', $messageId)->where('user_id', auth()->id())->exists();
    }

    public function chats(): HasManyThrough
    {
        return $this->hasManyThrough(
            Chat::class,      // Целевая модель, к которой идет связь
            ChatMember::class, // Промежуточная модель, через которую идет связь
            'user_id',        // Внешний ключ на промежуточной модели ChatMember, соответствующий User
            'id',             // Внешний ключ на целевой модели Chat, который связывает с ChatMember
            'id',             // Локальный ключ в модели User
            'chat_id'         // Локальный ключ в модели ChatMember, который связывается с Chat
        );
    }

    public function chatCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            ChatCategory::class,
            'chat_category_mappings',
            'user_id',
            'chat_category_id'
        )->withPivot('chat_id');
    }
}
