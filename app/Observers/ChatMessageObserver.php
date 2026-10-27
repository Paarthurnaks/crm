<?php

namespace App\Observers;

use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\UnreadChatMessage;

class ChatMessageObserver
{
    public function created(ChatMessage $chatMessage): void
    {
        $recipientIds = ChatMember::where('chat_id', $chatMessage->chat_id)->pluck('user_id');

        foreach ($recipientIds as $recipientId) {
            if ((int) $recipientId === (int) $chatMessage->user_id) {
                continue;
            }

            UnreadChatMessage::create([
                'chat_id' => $chatMessage->chat_id,
                'user_id' => $recipientId,
                'chat_message_id' => $chatMessage->id,
            ]);
        }
    }
}
