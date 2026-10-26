<?php

namespace App\Observers;

use App\Models\Deal;
use App\Models\DealEvent;
use App\Models\EntityActivity;
use App\Notifications\DealEventNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DealEventObserver
{
    public function created(DealEvent $dealEvent): void
    {
        EntityActivity::create([
            'entity_id' => $dealEvent->deal_id,
            'entity_type' => Deal::class,
            'activityable_type' => DealEvent::class,
            'activityable_id' => $dealEvent->id,
        ]);

        DB::afterCommit(function () use ($dealEvent): void {
            $deal = $dealEvent->deal;
            $recipients = collect([$deal->responsible_id, $deal->creator_id])
                ->filter(fn ($id) => $id && (int) $id !== (int) $dealEvent->initiator_id)
                ->unique();

            foreach ($recipients as $recipientId) {
                try {
                    $recipient = \App\Models\User::find($recipientId);
                    $recipient?->notify(new DealEventNotification($dealEvent));
                } catch (\Throwable $exception) {
                    Log::error('deal.event_notification.failed', [
                        'deal_id' => $dealEvent->deal_id,
                        'event_id' => $dealEvent->id,
                        'user_id' => $recipientId,
                        'exception' => $exception::class,
                    ]);
                }
            }
        });
    }
}
