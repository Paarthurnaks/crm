<?php

namespace App\Notifications;

use App\Enums\DealEventTypeEnum;
use App\Http\Resources\DealEventResource;
use App\Http\Resources\DealResource;
use App\Models\Deal;
use App\Models\DealEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\View;
use ReflectionClass;

class DealEventNotification extends Notification
{
    use Queueable;

    private string $html;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly DealEvent $dealEvent,
        private readonly array $data = []
    )
    {
        $this->init();
    }

    /**
     * @return void
     */
    public function init(): void
    {
        $name = $this->dealEvent->initiator->name;
        $surname = $this->dealEvent->initiator->surname;
        $id =  $this->dealEvent->deal->id;
        $title =  $this->dealEvent->deal->title;

        if(isset($data['oldResponsibleId'])) {
            $this->html = View::make('notification.deal.' . $this->dealEvent->type . '-removed', compact('name', 'surname', 'id', 'title'))->render();
        } else {
            $this->html = View::make('notification.deal.' . $this->dealEvent->type, compact('name', 'surname', 'id', 'title'))->render();
        }
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
            'item' =>  DealEventResource::make($this->dealEvent),
            'model' => DealResource::make($this->dealEvent->deal),
            'model_name' => (new ReflectionClass(Deal::class))->getShortName(),
            'event_type' => $this->dealEvent->type,
        ];
    }

    public function toBroadcast(): BroadcastMessage
    {
        return new BroadcastMessage([
            'html' => $this->html,
            'item' =>  DealEventResource::make($this->dealEvent),
            'model' => DealResource::make($this->dealEvent->deal),
            'model_name' => (new ReflectionClass(Deal::class))->getShortName(),
            'event_type' => $this->dealEvent->type,
        ]);
    }
}
