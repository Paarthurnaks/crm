<?php

namespace App\Enums;

enum UserTableSettingsEntityEnum: string
{
    case DEALS = 'deals';
    case TASKS = 'tasks';
    case USERS = 'users';
    case ACCOUNTS = 'accounts';
    case DOCUMENTS = 'documents';
    case BANKS = 'banks';
    case PARTNERS = 'partners';
    case ANALYTICS = 'analytics';
}
