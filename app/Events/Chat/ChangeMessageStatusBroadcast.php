<?php

namespace App\Events\Chat;

use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangeMessageStatusBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        private readonly ChatMessageResource $message,
        private readonly ChatResource $chat,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->chat->id}.user.{$this->message->user->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChangeMessageStatus';
    }

    public function broadcastWith(): array
    {
        return [
            'chat' => $this->chat,
            'message' => $this->message,
        ];
    }
}
