<?php

namespace App\Policies;

use App\Enums\DefaultRolesEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Models\Deal;
use App\Models\User;

class DealPolicy
{
    public function view(User $actor, Deal $deal): bool
    {
        if ($actor->hasRole(DefaultRolesEnum::CLIENT->value)) {
            return (string) $deal->getRawOriginal('client') === (string) $actor->id;
        }

        $scope = $actor->permissionScope(PermissionEntityEnum::DEALS->value, 'view');

        return match ($scope) {
            PermissionTypeEnum::ALL->value => true,
            PermissionTypeEnum::OWN->value => (int) $deal->creator_id === $actor->id
                || (int) $deal->responsible_id === $actor->id,
            PermissionTypeEnum::OPENED->value => $deal->salesStage()->where('type', 'default')->exists(),
            default => false,
        };
    }
}
