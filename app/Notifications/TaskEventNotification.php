<?php

namespace App\Notifications;

use App\Http\Resources\TaskEventResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\View;
use ReflectionClass;

class TaskEventNotification extends Notification
{
    use Queueable;

    private string $html;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly TaskEvent $taskEvent
    ) {
        $this->init();
    }

    public function init(): void
    {
        $name = $this->taskEvent->initiator?->name ?? 'System';
        $surname = $this->taskEvent->initiator?->surname;
        $id = $this->taskEvent->task->id;
        $title = $this->taskEvent->task->title;

        $this->html = View::make('notification.task.'.$this->taskEvent->type, compact('name', 'surname', 'id', 'title'))->render();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(): array
    {
        return [
            'html' => $this->html,
            'item' => TaskEventResource::make($this->taskEvent),
            'model' => TaskResource::make($this->taskEvent->task),
            'model_name' => (new ReflectionClass(Task::class))->getShortName(),
            'event_type' => $this->taskEvent->type,
        ];
    }

    public function toBroadcast(): BroadcastMessage
    {
        return new BroadcastMessage([
            'html' => $this->html,
            'item' => TaskEventResource::make($this->taskEvent),
            'model' => TaskResource::make($this->taskEvent->task),
            'model_name' => (new ReflectionClass(Task::class))->getShortName(),
            'event_type' => $this->taskEvent->type,
        ]);
    }
}
