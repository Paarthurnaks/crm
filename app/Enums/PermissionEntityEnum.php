<?php

namespace App\Enums;

enum PermissionEntityEnum: string
{
    case USERS = 'users';
    case DEALS = 'deals';
    case TASKS = 'tasks';
    case ANALYTICS = 'analytics';
    case DOCKET = 'docket';
    case HELPDESK = 'helpdesk';
    case KNOWLEDGE_BASE = 'knowledge_base';
    case PARTNERS_CHATS = 'partners_chats';
}
