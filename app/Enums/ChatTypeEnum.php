<?php

namespace App\Enums;

enum ChatTypeEnum: string
{
    case SINGLE = 'single'; // для чатов между пользователями esb
    case GROUP = 'group'; // для групповых чатов
    case DEFAULT = 'default'; // для дефолтного чата между всеми пользователями в esb
    case PARTNER = 'partner'; // для чатов esb с партнерами
    case CLIENT = 'client'; // для чатов партнеров с клиентами (в них также находятся юзеры esb, но их не видно)

    public static function systemTypes(): array
    {
        return [
            self::SINGLE->value,
            self::GROUP->value,
            self::DEFAULT->value,
        ];
    }

    public static function personalTypes(): array
    {
        return [
            self::PARTNER->value,
            self::CLIENT->value,
        ];
    }
}
