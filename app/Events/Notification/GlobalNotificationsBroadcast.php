<?php

namespace App\Events\Notification;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\SerializesModels;

class GlobalNotificationsBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private int $totalUnreadNotifications;

    /**
     * Create a new event instance.
     */
    public function __construct(
        private readonly DatabaseNotification $notification,
        private readonly int $userId
    ) {
        $this->init();
    }

    public function init(): void
    {
        $this->totalUnreadNotifications = User::find($this->userId)->unreadNotifications()->count();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('global-notifications.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GlobalNotifications';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => NotificationResource::make($this->notification),
            'total_unread_notifications' => $this->totalUnreadNotifications,
        ];
    }
}
