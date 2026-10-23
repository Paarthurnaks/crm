<?php

namespace App\Enums;

use ArchTech\Enums\Values;

enum EntityTypeEnum: string
{
    use Values;

    case DEALS = "deals";
    case TASKS = "tasks";
    case USERS = "users";
    case ACCOUNTS = "accounts";
    case DOCUMENTS = "documents";
    case BANKS = "banks";
    case PARTNERS = "partners";
    case ANALYTICS = "analytics";
}
