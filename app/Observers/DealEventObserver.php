<?php

namespace App\Observers;

use App\Models\Deal;
use App\Models\DealEvent;
use App\Models\EntityActivity;
use App\Notifications\DealEventNotification;

class DealEventObserver
{
    public function created(DealEvent $dealEvent): void
    {
        if ($dealEvent->deal->responsible_id && $dealEvent->deal->responsible_id !== auth()->id()) {
            $dealEvent->deal->responsible->notify(new DealEventNotification($dealEvent));
        }

        if ($dealEvent->deal->creator_id && $dealEvent->deal->creator_id !== auth()->id()) {
            $dealEvent->deal->creator->notify(new DealEventNotification($dealEvent, []));
        }

        EntityActivity::create([
            'entity_id' => $dealEvent->deal_id,
            'entity_type' => Deal::class,
            'activityable_type' => DealEvent::class,
            'activityable_id' => $dealEvent->id,
        ]);
    }
}
