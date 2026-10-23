<?php

namespace App\Events\Chat;

use App\Http\Resources\ChatResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GlobalChatsMessageDeleteBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        private readonly ChatResource $chat,
        private readonly int $messageId,
        private readonly int $userId
    )
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('global-chats.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GlobalChatsMessageDelete';
    }


    public function broadcastWith(): array
    {
        return [
            'deleted_message_id' => $this->messageId,
            'chat' => $this->chat,
        ];
    }
}
