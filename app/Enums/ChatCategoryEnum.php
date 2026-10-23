<?php

namespace App\Enums;

enum ChatCategoryEnum: string
{
    case SYSTEM_PARTNERS = 'system-partners'; // папка с партнерами для esb
    case SYSTEM_PARTNER_CLIENTS = 'system-partner-clients'; // папка с партнерами и клиентами для esb

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_PARTNERS => __('chat_categories.system-partners'),
            self::SYSTEM_PARTNER_CLIENTS => __('chat_categories.system-partner-clients'),
        };
    }

}
