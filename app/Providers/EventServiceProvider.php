<?php

namespace App\Providers;


use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Deal;
use App\Models\DealEvent;
use App\Models\Permission;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\User;
use App\Models\UserField;
use App\Observers\ChatMessageObserver;
use App\Observers\ChatObserver;
use App\Observers\CommentObserver;
use App\Models\DiskEntity;
use App\Observers\DealEventObserver;
use App\Observers\DealObserver;
use App\Observers\NotificationObserver;
use App\Observers\PermissionObserver;
use App\Observers\TaskEventObserver;
use App\Observers\DiskEntityObserver;
use App\Observers\TaskObserver;
use App\Observers\UserFieldObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\DatabaseNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Deal::observe(DealObserver::class);
        User::observe(UserObserver::class);
        Task::observe(TaskObserver::class);
        ChatMessage::observe(ChatMessageObserver::class);
        DealEvent::observe(DealEventObserver::class);
        TaskEvent::observe(TaskEventObserver::class);
        Comment::observe(CommentObserver::class);
        DiskEntity::observe(DiskEntityObserver::class);
        DatabaseNotification::observe(NotificationObserver::class);
        Permission::observe(PermissionObserver::class);
        UserField::observe(UserFieldObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
