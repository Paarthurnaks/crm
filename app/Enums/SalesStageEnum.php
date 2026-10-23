<?php

namespace App\Enums;

enum SalesStageEnum: string
{
    case DEFAULT = 'default';
    case SUCCESS = 'success';
    case UNSUCCESS = 'unsuccess';
}
