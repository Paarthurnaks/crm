<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function view(User $actor, Report $report): bool
    {
        return $report->canBeViewedBy($actor->id);
    }
}
