<?php
namespace App\Enums;
enum ChatMessageTypeEnum: string
{
    case SENT = "sent";

    case DELIVERED = "delivered";

    case READ = "read";
}
