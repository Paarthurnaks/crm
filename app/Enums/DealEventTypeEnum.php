<?php

namespace App\Enums;

enum DealEventTypeEnum: string
{
    case NEW_DEAL = "new_deal";
    case CHANGE_FIELD = "change_field";
    case CHANGE_STAGE = "change_stage";
    case CHANGE_RESPONSIBLE = "change_responsible";
    case CHANGE_CLIENT = "change_client";
}
