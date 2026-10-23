<?php

namespace App\Enums;

enum TaskStatusEnum: string
{
    case NEW = "new";
    case PENDING = "pending";
    case PAUSED = "paused";
    case CLOSED = "closed";
    case EXPIRED = "expired";

    public function getTitle(): string
    {
        return match($this) {
            self::NEW => 'Новая',
            self::PENDING => 'В работе',
            self::PAUSED => 'Приостановлена',
            self::CLOSED => 'Закрыта',
            self::EXPIRED => 'Просрочена',
        };
    }

    public static function getOptions(): array
    {
        return [
            [
                "title" => self::NEW->getTitle(),
                "option" => self::NEW,
            ],
            [
                "title" => self::PENDING->getTitle(),
                "option" => self::PENDING,
            ],
            [
                "title" => self::PAUSED->getTitle(),
                "option" => self::PAUSED,
            ],
            [
                "title" => self::CLOSED->getTitle(),
                "option" => self::CLOSED,
            ],
            [
                "title" => self::EXPIRED->getTitle(),
                "option" => self::EXPIRED,
            ],
        ];
    }
}
