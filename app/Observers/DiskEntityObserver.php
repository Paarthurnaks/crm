<?php

namespace App\Observers;

use App\Models\DiskEntity;
use Illuminate\Support\Facades\Auth;

class DiskEntityObserver
{
    public function updating(DiskEntity $diskEntity): void
    {
        $diskEntity->who_changed_id = Auth::user()->id;
        $diskEntity->updated_at = now();
    }
}
