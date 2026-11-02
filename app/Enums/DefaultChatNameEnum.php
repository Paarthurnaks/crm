<?php

namespace App\Enums;

enum DefaultChatNameEnum: string
{
    case ESB_SYSTEM = 'CRM Система';
    case DEFAULT = 'Общий чат';
    case DELETED = 'Чат удален';
    case DELETED_PARTNER = 'Партнер удален';
    case DELETED_CLIENT = 'Клиент удален';
    case DELETED_USER = 'Пользователь удален';
}
