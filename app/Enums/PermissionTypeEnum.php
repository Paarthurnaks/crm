<?php

namespace App\Enums;

enum PermissionTypeEnum: string
{
    case ALL = "all";
    case OPENED = "opened";
    case OWN = "own";
    case FORBIDDEN = "forbidden";
}