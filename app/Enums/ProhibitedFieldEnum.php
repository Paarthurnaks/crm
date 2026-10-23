<?php

namespace App\Enums;

use JetBrains\PhpStorm\Pure;

enum ProhibitedFieldEnum: string
{
    case CREATOR = 'creator';
    case CREATED_AT = 'created_at';
    case UPDATED_AT = 'updated_at';
    case WHO_CHANGED = 'who_changed';

    #[Pure]
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
