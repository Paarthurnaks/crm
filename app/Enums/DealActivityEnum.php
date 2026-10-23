<?php

namespace App\Enums;

enum DealActivityEnum: string
{
    case COMMENT = "comment";
    case TASK = "task";
    case EVENT = "event";
}
