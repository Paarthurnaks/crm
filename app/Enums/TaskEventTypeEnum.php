<?php

namespace App\Enums;

enum TaskEventTypeEnum: string
{
    case NEW_TASK = "new_task";
    case CHANGE_FIELD = "change_field";
    case CHANGE_STATUS = "change_status";
    case NEW_DOCKET = "new_docket";
}
