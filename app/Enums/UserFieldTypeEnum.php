<?php

namespace App\Enums;

use ArchTech\Enums\Values;

enum UserFieldTypeEnum: string
{
    use Values;

    case INTEGER = "integer";
    case FLOAT = "float";
    case BOOLEAN = "boolean";
    case STRING = "string";
    case DATETIME = "datetime";
    case DATE = "date";
    case ENUM = "enum";
    case FILE = "file";
    case USER = "user";
    case DEAL = "deal";
}
