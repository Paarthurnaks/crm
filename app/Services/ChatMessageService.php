<?php

namespace App\Services;

use App\Enums\ChatMessageSearchTypeEnum;
use App\Enums\ChatMessageTypeEnum;
use App\Enums\ChatTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Events\Chat\ChangeMessageStatusBroadcast;
use App\Events\Chat\ChatMessageDeleteBroadcast;
use App\Events\Chat\GlobalChatsMessageDeleteBroadcast;
use App\Events\Chat\GlobalChatsMessageSentBroadcast;
use App\Events\Chat\GlobalChatsMessageStatusChangeBroadcast;
use App\Events\Chat\GlobalChatsMessageUpdateBroadcast;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use App\Models\ChatHiddenMessage;
use App\Models\ChatMessage;
use App\Models\ForwardingChatMessage;
use App\Models\UnreadChatMessage;
use Exception;
use Illuminate\Support\Collection;


class ChatMessageService {
    private const ERROR_MESSAGES = [
        'chatMemberError' => 'Пользователь не является участником чата',
        'chatNotFound' => 'Чат не существует',
        'clientChatError' => 'Только партнер и клиент могут писать в этот чат',
    ];

    /**
     * @param int $chatId
     * @param array $data
     * @return array
     */
    public function getMessagesList(int $chatId, array $data): array
    {
        $limit = config('chats.messages_limit');
        $additionalCount = 1; // дополнительное сообщение для проверки наличия следующих сообщений
        $data['search_term'] = $data['search_term'] ?? null;

        if(!isset($data['direction'])) {
            $messages = ChatMessage::where('chat_id', $chatId)
                            ->when($data['search_term'], function($query) use ($data) {
                                $query->whereRaw('LOWER(text) LIKE ?', [mb_strtolower("%{$data['search_term']}%")]);
                            })
                            ->limit($limit + $additionalCount)
                            ->orderBy('id', 'desc')
                            ->get();

            $hasMoreUpward = $messages->count() > $limit;
            if($hasMoreUpward) $messages->pop();

            return [
                'messages' => $messages->map(function ($message) {
                    return new ChatMessageResource($message, auth()->id());
                }),
                'has_more_upward' => $hasMoreUpward,
                'has_more_downward' => false,
            ];
        }

        $upwardMessages = $this->getUpwardMessages($chatId, $data, $limit + $additionalCount);
        $downwardMessages = $this->getDownwardMessages($chatId, $data, $limit + $additionalCount);

        $hasMoreUpward = $upwardMessages->count() > $limit;
        $hasMoreDownward = $downwardMessages->count() > $limit;

        if ($hasMoreUpward) $upwardMessages->pop();
        if ($hasMoreDownward) $downwardMessages->shift();

        $lastMessage = collect();
        $messages = collect();

        if ($data['direction'] === ChatMessageSearchTypeEnum::BOTH->value ||
            $data['direction'] === ChatMessageSearchTypeEnum::UPWARD->value ||
            $data['direction'] === ChatMessageSearchTypeEnum::DOWNWARD->value) {
            $lastMessage = ChatMessage::where('chat_id', $chatId)
                ->where('id', $data['last_message_id'])
                ->get();
        }

        if ($data['direction'] === ChatMessageSearchTypeEnum::UPWARD->value ||
            $data['direction'] === ChatMessageSearchTypeEnum::BOTH->value) {
            $messages = $messages->concat($upwardMessages);
        }

        if ($data['direction'] === ChatMessageSearchTypeEnum::BOTH->value) {
            $messages = $messages->concat($lastMessage);
        }

        if ($data['direction'] === ChatMessageSearchTypeEnum::DOWNWARD->value ||
            $data['direction'] === ChatMessageSearchTypeEnum::BOTH->value) {
            $messages = $messages->concat($downwardMessages);
        }

        return [
            'messages' => $messages->map(function ($message) {
                return new ChatMessageResource($message, auth()->id());
            }),
            'has_more_upward' => $hasMoreUpward,
            'has_more_downward' => $hasMoreDownward
        ];
    }

    /**
     * @param int $chatId
     * @param array $data
     * @param int $limit
     * @return Collection
     */
    private function getUpwardMessages(int $chatId, array $data, int $limit): Collection
    {
        return ChatMessage::where('chat_id', $chatId)
            ->when($data['search_term'], function($query) use ($data) {
                $query->whereRaw('LOWER(text) LIKE ?', [mb_strtolower("%{$data['search_term']}%")]);
            })
            ->where('id', '<', $data['last_message_id'])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }


    /**
     * @param int $chatId
     * @param array $data
     * @param int $limit
     * @return Collection
     */
    private function getDownwardMessages(int $chatId, array $data, int $limit): Collection
    {
        return ChatMessage::where('chat_id', $chatId)
            ->when($data['search_term'], function($query) use ($data) {
                $query->whereRaw('LOWER(text) LIKE ?', [mb_strtolower("%{$data['search_term']}%")]);
            })
            ->where('id', '>', $data['last_message_id'])
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }


    /**
     * @param array $data
     * @param int $chatId
     * @return ChatMessage
     * @throws Exception
     */
    public function sendMessage(array $data, int $chatId): ChatMessage
    {
        if(!auth()->user()->isMemberOfChat($chatId)) throw new Exception(self::ERROR_MESSAGES['chatMemberError']);

        $chat = Chat::find($chatId);

        if(!$chat) throw new Exception(self::ERROR_MESSAGES['chatNotFound']);

        if($chat->type === ChatTypeEnum::CLIENT->value && !auth()->user()->hasRole(DefaultRolesEnum::havePersonalAccount())) {
            throw new Exception(self::ERROR_MESSAGES['clientChatError']);
        }

        $message = ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => auth()->id(),
            'status' => ChatMessageTypeEnum::SENT->value,
            'text' => $data['text'] ?? null,
            'reply_message_id' => $data['reply_message_id'] ?? null,
            'is_forwarded' => $data['is_forwarded'] ?? false,
        ]);

        if (isset($data['attachments'])) {
            foreach ($data['attachments'] as $attachment) {
                $message->addMedia($attachment)
                    ->withCustomProperties(['chat_id' => $chat->id])
                    ->toMediaCollection();
            }
        }

        $this->broadcastMessageSentToGlobal($message);

        return $message;
    }

    /**
     * @param ChatMessage $message
     * @return void
     */
    public function broadcastMessageSentToGlobal(ChatMessage $message): void
    {
        if ($message->status !== ChatMessageTypeEnum::DELIVERED->value) {
            $message->status = ChatMessageTypeEnum::DELIVERED->value;
            $message->save();
        }

        $chat = $message->chat;
        $chat->load(
            'lastMessage.chat',
            'lastMessage.user',
            'lastMessage.forward',
            'category',
            'unreadMessages.message.user',
            'unreadMessages.message.chat',
            'members.user.role',
            'messages.forward',
        );

        $chatResources = $chat->members
            ->filter(function ($member) {
                return $member->user_id !== auth()->id();
            })
            ->mapWithKeys(function ($member) use ($chat, $message) {
                $chatResource = ChatResource::make($chat, $member->user_id);

                return [$member->user_id => [
                    'chat' => $chatResource,
                    'message' => ChatMessageResource::make($message, $member->user_id, $chatResource),
                ]];
            });

        $message->chat->members->each(function ($member) use ($chatResources, $message) {
            if($member->user_id !== auth()->id()) {
                $chatResource = $chatResources[$member->user_id]['chat'];
                $chatMessageResource = $chatResources[$member->user_id]['message'];

                try {
                    broadcast(new GlobalChatsMessageSentBroadcast(
                        $chatMessageResource,
                        $chatResource,
                        $member->user_id
                    ));
                } catch (\Exception $e) {
                    \Log::error('Ошибка при broadcast для пользователя ' . $member->user_id . ': ' . $e->getMessage());
                }
            }
        });
    }

    /**
     * @param array $data
     * @param ChatMessage $message
     * @return bool
     * @throws Exception
     */
    public function updateMessage(array $data, ChatMessage $message): bool
    {
        if(!auth()->user()->isAuthorOfChatMessage($message->id)) {
            throw new Exception(self::ERROR_MESSAGES['chatMemberError']);
        }

        $isUpdated = $message->update(['text' => $data['text']]);

        $this->broadcastMessageUpdateToGlobal($message);

        return $isUpdated;
    }

    /**
     * @param $message
     * @return void
     */
    public function broadcastMessageUpdateToGlobal($message): void
    {
        $message->chat->load('lastMessage.chat', 'lastMessage.user', 'category', 'unreadMessages.message.user', 'members.user.role', 'forward');
        $message->chat->members->each(function($member) use ($message) {
            if($member->user_id !== auth()->id()) {
                broadcast(
                    new GlobalChatsMessageUpdateBroadcast(
                        ChatMessageResource::make($message, $member->user_id),
                        ChatResource::make($message->chat, $member->user_id),
                        $member->user_id
                    )
                );
            }
        });
    }

    /**
     * @param array $data
     * @param int $chatId
     * @return Collection
     */
    public function updateMessageStatus(array $data, int $chatId): Collection
    {
        $userId = auth()->id();
        $messageIds = $data['message_ids'];

        $messages = ChatMessage::with([
                'chat' => [
                    'lastMessage' => [
                        'chat',
                        'user',
                        'forward',
                    ],
                    'category',
                    'members.user.role',
                ],
                'replyTo',
                'forward',
            ])
            ->whereIn('id', $messageIds)
            ->get();

        $lastMessageId = ChatMessage::where('chat_id', $chatId)
            ->latest('id')
            ->value('id');

        UnreadChatMessage::where('user_id', $userId)
            ->whereIn('chat_message_id', $messageIds)
            ->delete();


        $updatedMessages = $messages->map(function ($message) use ($lastMessageId, $userId) {
            if ($message->user_id !== $userId && $message->status !== ChatMessageTypeEnum::READ->value) {
                $message->status = ChatMessageTypeEnum::READ->value;
            }

            $chatResource = ChatResource::make($message->chat, $message->user_id);
            $messageResource = ChatMessageResource::make($message, $message->user_id);

            broadcast(new ChangeMessageStatusBroadcast($messageResource, $chatResource));

            if ($message->id === $lastMessageId) {
                broadcast(new GlobalChatsMessageStatusChangeBroadcast($messageResource, $chatResource));
            }

            return $message;
        });

        ChatMessage::whereIn('id', $messageIds)
            ->where('user_id', '!=', $userId)
            ->where('status', '!=', ChatMessageTypeEnum::READ->value)
            ->update(['status' => ChatMessageTypeEnum::READ->value]);

        return $updatedMessages;
    }

    /**
     * @param int $messageId
     * @return void
     */
    public function hideMessage(int $messageId): void
    {
        ChatHiddenMessage::create([
            'chat_message_id' => $messageId,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * @param ChatMessage $message
     * @return void
     */
    public function destroy(ChatMessage $message): void
    {
        $chat = $message->chat;
        $messageId = $message->id;

        broadcast(
            new ChatMessageDeleteBroadcast(
                ChatResource::make($chat),
                $messageId
            )
        )->toOthers();

        $this->broadcastMessageDeleteToGlobal($chat->id, $messageId);

        $message->delete();
    }

    /**
     * @param $chatId
     * @param $messageId
     * @return void
     */
    private function broadcastMessageDeleteToGlobal($chatId, $messageId): void
    {
        Chat::find($chatId)->members->each(function($member) use($chatId, $messageId) {
            if($member->user_id !== auth()->id()) {
                broadcast(
                    new GlobalChatsMessageDeleteBroadcast(
                        new ChatResource($member->chat, $member->userId),
                        $messageId,
                        $member->user_id
                    )
                );
            }
        });
    }

    /**
     * @param array $data
     * @return Collection
     * @throws Exception
     */
    public function forwardMessage(array $data): Collection
    {
        $messages = ChatMessage::find($data['message_ids']);

        $result = [];

        foreach($data['chat_ids'] as $chatId) {
            foreach ($messages as $message) {
                $newMessageData = [
                    'chat_id' => $chatId,
                    'user_id' => auth()->id(),
                    'status' => ChatMessageTypeEnum::SENT->value,
                    'text' => $message->text,
                    'is_forwarded' => true,
                    'reply_message_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $forwardedMessage = $this->sendMessage($newMessageData, $chatId);

                foreach ($message->getMedia() as $media) {
                    $media->copy($forwardedMessage);
                }

                ForwardingChatMessage::create([
                    'original_message_id' => $message->forward()->exists() ? $message->forward->original_message_id : $message->id,
                    'forwarded_message_id' => $forwardedMessage->id,
                ]);

                $result[] = $forwardedMessage;
            }
        }

        return collect($result);
    }
}
