<?php

namespace App\Events\Chat;

use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GlobalChatsMessageStatusChangeBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        private readonly ChatMessageResource $message,
        private readonly ChatResource $chat,
    )
    {

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('global-chats.' . $this->message->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GlobalChatsMessageStatusChange';
    }


    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'chat' => $this->chat,
        ];
    }
}
