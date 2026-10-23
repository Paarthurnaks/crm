<?php

namespace App\Enums;

enum TaskTypeEnum: string
{
    case STANDARD = 'standard';
    case DOCKET = 'docket';
    case HELPDESK = 'helpdesk';

    public function getTitle(): string
    {
        return match ($this) {
            self::STANDARD => 'Стандартная',
            self::DOCKET => 'Судебное дело',
            self::HELPDESK => 'HELPDESK',
        };
    }

    public static function getOptions(): array
    {
        return [
            [
                'title' => self::STANDARD->getTitle(),
                'option' => self::STANDARD,
            ],
            [
                'title' => self::DOCKET->getTitle(),
                'option' => self::DOCKET,
            ],
            [
                'title' => self::HELPDESK->getTitle(),
                'option' => self::HELPDESK,
            ],
        ];
    }
}
