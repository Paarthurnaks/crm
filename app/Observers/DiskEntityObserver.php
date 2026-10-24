<?php

namespace App\Observers;

use App\Models\DiskEntity;
use Illuminate\Support\Facades\Auth;

class DiskEntityObserver
{
    public function updating(DiskEntity $diskEntity): void
    {
        if (($actorId = Auth::id()) !== null) {
            $diskEntity->who_changed_id = $actorId;
        }

        $diskEntity->updated_at = now();
    }
}
