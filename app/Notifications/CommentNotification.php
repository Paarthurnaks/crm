<?php

namespace App\Notifications;

use App\Http\Resources\CommentResource;
use App\Http\Resources\DealResource;
use App\Http\Resources\TaskResource;
use App\Models\Deal;
use App\Models\Task;
use BeyondCode\Comments\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\View;
use ReflectionClass;

class CommentNotification extends Notification
{
    use Queueable;

    private string $html;

    private string $modelName;

    private JsonResource $modelResource;

    private static array $resourceMap = [
        Deal::class => DealResource::class,
        Task::class => TaskResource::class,
    ];

    public function __construct(
        private readonly Comment $comment,
        private readonly Model $model
    ) {
        $this->modelName = (new ReflectionClass(get_class($this->model)))->getShortName();

        $this->init();
        $this->initModelResource();
    }

    public function init(): void
    {
        $name = $this->comment->commentator->name;
        $surname = $this->comment->commentator->surname;
        $id = $this->model->id;
        $title = $this->model->title;

        $this->html = View::make('notification.comment.'.strtolower($this->modelName), compact('name', 'surname', 'id', 'title'))->render();
    }

    private function initModelResource(): void
    {
        $modelClass = get_class($this->model);

        $resourceClass = self::$resourceMap[$modelClass];

        $this->modelResource = new $resourceClass($this->model);
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(): array
    {
        return [
            'html' => $this->html,
            'item' => CommentResource::make($this->comment),
            'model' => $this->modelResource,
            'model_name' => $this->modelName,
            'event_type' => 'new_comment',
        ];
    }

    public function toBroadcast(): BroadcastMessage
    {
        return new BroadcastMessage([
            'html' => $this->html,
            'item' => CommentResource::make($this->comment),
            'model' => $this->modelResource,
            'model_name' => $this->modelName,
            'event_type' => 'new_comment',
        ]);
    }
}
