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
use Closure;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatMessageService
{
    private const ERROR_MESSAGES = [
        'chatMemberError' => 'Пользователь не является участником чата',
        'chatNotFound' => 'Чат не существует',
        'clientChatError' => 'Только партнер и клиент могут писать в этот чат',
    ];

    public function getMessagesList(int $chatId, array $data): array
    {
        $limit = config('chats.messages_limit');
        $additionalCount = 1; // дополнительное сообщение для проверки наличия следующих сообщений
        $data['search_term'] = $data['search_term'] ?? null;

        if (! isset($data['direction'])) {
            $messages = ChatMessage::where('chat_id', $chatId)
                ->when($data['search_term'], function ($query) use ($data) {
                    $query->whereRaw('LOWER(text) LIKE ?', [mb_strtolower("%{$data['search_term']}%")]);
                })
                ->limit($limit + $additionalCount)
                ->orderBy('id', 'desc')
                ->get();

            $hasMoreUpward = $messages->count() > $limit;
            if ($hasMoreUpward) {
                $messages->pop();
            }

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

        if ($hasMoreUpward) {
            $upwardMessages->pop();
        }
        if ($hasMoreDownward) {
            $downwardMessages->shift();
        }

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
            'has_more_downward' => $hasMoreDownward,
        ];
    }

    private function getUpwardMessages(int $chatId, array $data, int $limit): Collection
    {
        return ChatMessage::where('chat_id', $chatId)
            ->when($data['search_term'], function ($query) use ($data) {
                $query->whereRaw('LOWER(text) LIKE ?', [mb_strtolower("%{$data['search_term']}%")]);
            })
            ->where('id', '<', $data['last_message_id'])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getDownwardMessages(int $chatId, array $data, int $limit): Collection
    {
        return ChatMessage::where('chat_id', $chatId)
            ->when($data['search_term'], function ($query) use ($data) {
                $query->whereRaw('LOWER(text) LIKE ?', [mb_strtolower("%{$data['search_term']}%")]);
            })
            ->where('id', '>', $data['last_message_id'])
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * @throws Exception
     */
    public function sendMessage(array $data, int $chatId): ChatMessage
    {
        if (! auth()->user()->isMemberOfChat($chatId)) {
            throw new Exception(self::ERROR_MESSAGES['chatMemberError']);
        }

        $chat = Chat::find($chatId);

        if (! $chat) {
            throw new Exception(self::ERROR_MESSAGES['chatNotFound']);
        }

        if ($chat->type === ChatTypeEnum::CLIENT->value && ! auth()->user()->hasRole(DefaultRolesEnum::havePersonalAccount())) {
            throw new Exception(self::ERROR_MESSAGES['clientChatError']);
        }

        $actorId = auth()->id();
        Log::info('chat.message.send.started', ['chat_id' => $chatId, 'actor_id' => $actorId]);

        try {
            return DB::transaction(function () use ($data, $chat, $actorId): ChatMessage {
                $message = ChatMessage::create([
                    'chat_id' => $chat->id,
                    'user_id' => $actorId,
                    'status' => ChatMessageTypeEnum::DELIVERED->value,
                    'text' => $data['text'] ?? null,
                    'reply_message_id' => $data['reply_message_id'] ?? null,
                    'is_forwarded' => $data['is_forwarded'] ?? false,
                ]);

                foreach ($data['attachments'] ?? [] as $attachment) {
                    $message->addMedia($attachment)
                        ->withCustomProperties(['chat_id' => $chat->id])
                        ->toMediaCollection();
                }

                DB::afterCommit(function () use ($message, $actorId): void {
                    try {
                        $this->broadcastMessageSentToGlobal($message, $actorId);
                    } catch (\Throwable $exception) {
                        Log::error('chat.message.broadcast.failed', ['chat_id' => $message->chat_id, 'message_id' => $message->id, 'exception' => $exception::class]);
                    }
                });
                Log::info('chat.message.send.completed', ['chat_id' => $chat->id, 'message_id' => $message->id]);

                return $message;
            });
        } catch (\Throwable $exception) {
            Log::error('chat.message.send.failed', ['chat_id' => $chatId, 'actor_id' => $actorId, 'exception' => $exception::class]);
            throw $exception;
        }
    }

    public function broadcastMessageSentToGlobal(ChatMessage $message, ?int $actorId = null): void
    {
        $actorId ??= auth()->id();

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

        $this->broadcastToRecipients($chat, $actorId, $message->id, 'sent', function (int $recipientId) use ($chat, $message) {
            $chatResource = ChatResource::make($chat, $recipientId);

            return new GlobalChatsMessageSentBroadcast(
                ChatMessageResource::make($message, $recipientId, $chatResource),
                $chatResource,
                $recipientId
            );
        });
    }

    /**
     * @throws Exception
     */
    public function updateMessage(array $data, ChatMessage $message): bool
    {
        if (! auth()->user()->isAuthorOfChatMessage($message->id)) {
            throw new Exception(self::ERROR_MESSAGES['chatMemberError']);
        }

        return DB::transaction(function () use ($message, $data): bool {
            $isUpdated = $message->update(['text' => $data['text']]);

            if ($isUpdated) {
                DB::afterCommit(function () use ($message): void {
                    try {
                        $this->broadcastMessageUpdateToGlobal($message);
                    } catch (\Throwable $exception) {
                        Log::error('chat.message_update.broadcast.failed', ['chat_id' => $message->chat_id, 'message_id' => $message->id, 'exception' => $exception::class]);
                    }
                });
                Log::info('chat.message.updated', ['chat_id' => $message->chat_id, 'message_id' => $message->id]);
            }

            return $isUpdated;
        });
    }

    public function broadcastMessageUpdateToGlobal($message): void
    {
        $message->chat->load('lastMessage.chat', 'lastMessage.user', 'category', 'unreadMessages.message.user', 'members.user.role', 'forward');
        $this->broadcastToRecipients($message->chat, auth()->id(), $message->id, 'updated', function (int $recipientId) use ($message) {
            return new GlobalChatsMessageUpdateBroadcast(
                ChatMessageResource::make($message, $recipientId),
                ChatResource::make($message->chat, $recipientId),
                $recipientId
            );
        });
    }

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
            ->where('chat_id', $chatId)
            ->whereIn('id', $messageIds)
            ->get();

        $selectedMessageIds = $messages->pluck('id');
        Log::debug('Chat messages selected for status update', ['chat_id' => $chatId, 'message_count' => $selectedMessageIds->count()]);

        $lastMessageId = ChatMessage::where('chat_id', $chatId)
            ->latest('id')
            ->value('id');

        return DB::transaction(function () use ($chatId, $userId, $selectedMessageIds, $messages, $lastMessageId): Collection {
            UnreadChatMessage::where('user_id', $userId)
                ->whereIn('chat_message_id', $selectedMessageIds)
                ->delete();

            ChatMessage::where('chat_id', $chatId)
                ->whereIn('id', $selectedMessageIds)
                ->where('user_id', '!=', $userId)
                ->where('status', '!=', ChatMessageTypeEnum::READ->value)
                ->update(['status' => ChatMessageTypeEnum::READ->value]);

            $messages->each(function (ChatMessage $message) use ($userId): void {
                if ($message->user_id !== $userId) {
                    $message->status = ChatMessageTypeEnum::READ->value;
                }
            });

            DB::afterCommit(function () use ($messages, $lastMessageId, $chatId): void {
                foreach ($messages as $message) {
                    try {
                        $chatResource = ChatResource::make($message->chat, $message->user_id);
                        $messageResource = ChatMessageResource::make($message, $message->user_id);
                        broadcast(new ChangeMessageStatusBroadcast($messageResource, $chatResource));

                        if ($message->id === $lastMessageId) {
                            broadcast(new GlobalChatsMessageStatusChangeBroadcast($messageResource, $chatResource));
                        }
                    } catch (\Throwable $exception) {
                        Log::error('chat.message_status.broadcast.failed', ['chat_id' => $chatId, 'message_id' => $message->id, 'exception' => $exception::class]);
                    }
                }
            });

            Log::debug('chat.message_status.updated', ['chat_id' => $chatId, 'message_count' => $messages->count()]);

            return $messages;
        });
    }

    public function hideMessage(int $messageId): void
    {
        ChatHiddenMessage::create([
            'chat_message_id' => $messageId,
            'user_id' => auth()->id(),
        ]);
    }

    public function destroy(ChatMessage $message): void
    {
        $chat = $message->chat;
        $messageId = $message->id;
        $actorId = auth()->id();

        DB::transaction(function () use ($message, $chat, $messageId, $actorId): void {
            $message->delete();

            DB::afterCommit(function () use ($chat, $messageId, $actorId): void {
                try {
                    broadcast(new ChatMessageDeleteBroadcast(ChatResource::make($chat), $messageId))->toOthers();
                    $this->broadcastMessageDeleteToGlobal($chat, $messageId, $actorId);
                } catch (\Throwable $exception) {
                    Log::error('chat.message_delete.broadcast.failed', ['chat_id' => $chat->id, 'message_id' => $messageId, 'exception' => $exception::class]);
                }
            });

            Log::info('chat.message.deleted', ['chat_id' => $chat->id, 'message_id' => $messageId]);
        });
    }

    private function broadcastMessageDeleteToGlobal(Chat $chat, int $messageId, ?int $actorId): void
    {
        $chat->load('members.user.role', 'unreadMessages.message.user', 'lastMessage', 'category');
        $this->broadcastToRecipients($chat, $actorId, $messageId, 'deleted', function (int $recipientId) use ($chat, $messageId) {
            return new GlobalChatsMessageDeleteBroadcast(new ChatResource($chat, $recipientId), $messageId, $recipientId);
        });
    }

    private function broadcastToRecipients(Chat $chat, ?int $actorId, int $messageId, string $action, Closure $eventForRecipient): void
    {
        foreach ($chat->members as $member) {
            $recipientId = (int) $member->user_id;
            if ($recipientId === $actorId) {
                continue;
            }

            try {
                broadcast($eventForRecipient($recipientId));
                Log::info('chat.message.broadcast.sent', ['chat_id' => $chat->id, 'message_id' => $messageId, 'user_id' => $recipientId, 'action' => $action]);
            } catch (\Throwable $exception) {
                Log::error('chat.message.broadcast.failed', ['chat_id' => $chat->id, 'message_id' => $messageId, 'user_id' => $recipientId, 'action' => $action, 'exception' => $exception::class]);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function forwardMessage(array $data): Collection
    {
        $messages = ChatMessage::find($data['message_ids']);

        return DB::transaction(function () use ($data, $messages): Collection {
            $result = [];

            foreach ($data['chat_ids'] as $chatId) {
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

            Log::info('chat.messages.forwarded', ['source_count' => $messages->count(), 'created_count' => count($result)]);

            return collect($result);
        });
    }
}
