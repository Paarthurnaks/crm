<?php

namespace App\Enums;

enum DiskEntityTypeEnum: string
{
    case FOLDER = 'Folder';
    case FILE = 'File';

    public function label(): string
    {
        return match ($this) {
            self::FOLDER => __('disk_entities.Folder'),
            self::FILE => __('disk_entities.File'),
        };
    }
}
