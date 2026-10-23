<?php

namespace App\Services;

use App\Models\DealEvent;
use Illuminate\Database\Eloquent\Collection;

class DealEventService
{
    public function getDealEvents(int $dealId): Collection
    {
        return DealEvent::where("deal_id", $dealId)->get();
    }
}
