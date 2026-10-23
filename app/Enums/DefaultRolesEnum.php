<?php

namespace App\Enums;

enum DefaultRolesEnum: string
{
    case SUPER_ADMIN = 'Superadmin';
    case PARTNER = 'Partner';
    case CLIENT = 'Client';

    public static function havePersonalAccount(): array
    {
        return [
            self::PARTNER->value,
            self::CLIENT->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::PARTNER => __('roles.Partner'),
            self::CLIENT => __('roles.Client'),
            self::SUPER_ADMIN => __('roles.Superadmin'),
        };
    }
}
