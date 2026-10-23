<?php

namespace App\Enums;

enum UserOnlyParamTypeEnum: string
{
    case PARTNERS = 'partners';
    case CLIENTS = 'clients';
    case WITHOUT_CLIENTS = 'without_clients';
    case WITHOUT_PERSONALS = 'without_personals';
}
