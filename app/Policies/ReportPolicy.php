<?php

namespace App\Policies;

use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function view(User $actor, Report $report): bool
    {
        return $report->canBeViewedBy($actor->id);
    }

    public function update(User $actor, Report $report): bool
    {
        return $report->author_id === $actor->id
            || $actor->hasAccess(PermissionEntityEnum::ANALYTICS->value, 'update', PermissionTypeEnum::ALL->value);
    }
}
