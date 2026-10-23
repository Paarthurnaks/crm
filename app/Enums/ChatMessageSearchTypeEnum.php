<?php
namespace App\Enums;
enum ChatMessageSearchTypeEnum: string
{
    case UPWARD = "upward";
    case DOWNWARD = "downward";
    case BOTH = "both";
}
