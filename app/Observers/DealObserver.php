<?php

namespace App\Observers;

use App\Models\Deal;

class DealObserver
{
    public function deleting(Deal $deal): void
    {
        $deal->activities()->delete();
    }
}
