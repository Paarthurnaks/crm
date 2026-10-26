<?php

namespace App\Services;

use App\Enums\DefaultRolesEnum;
use App\Models\User;

class FieldAccess
{
    public static function supports(string $action): bool
    {
        return in_array($action, ['view', 'create', 'update'], true);
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return $user?->role !== null && $user->hasRole(DefaultRolesEnum::SUPER_ADMIN->value);
    }

    /** @return array{fields: array<int, int>, user_fields: array<int, int>} */
    public static function allowedIds(?User $user, string $action): array
    {
        if (! self::supports($action) || ! $user?->role) {
            return ['fields' => [], 'user_fields' => []];
        }

        return [
            'fields' => $user->role->fields()->wherePivot('type', $action)->pluck('fields.id')->all(),
            'user_fields' => $user->role->userFields()->wherePivot('type', $action)->pluck('user_fields.id')->all(),
        ];
    }
}
