<?php

namespace App\Mappers;

class ActionToPermissionMapper
{
    public function map(string $actionName): ?string
    {
        return match ($actionName) {
            'index', 'show' => 'view',
            'store' => 'create',
            'update' => 'update',
            'destroy' => 'delete',
            default => null,
        };
    }
}
