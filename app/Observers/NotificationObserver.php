<?php

namespace App\Observers;

use App\Events\Notification\GlobalNotificationsBroadcast;
use Illuminate\Notifications\DatabaseNotification;

class NotificationObserver
{
    public function created(DatabaseNotification $databaseNotification): void
    {
        $userId = $databaseNotification->notifiable_id;

        broadcast(new GlobalNotificationsBroadcast($databaseNotification, $userId));
    }

}
