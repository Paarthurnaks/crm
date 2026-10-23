<?php

namespace App\Observers;

use App\Enums\UserFieldTypeEnum;
use App\Models\UserField;
use App\Services\UserFieldService;

class UserFieldObserver
{
    public function __construct(
        private readonly UserFieldService $userFieldService
    ) {}

    public function created(UserField $userField): void
    {
        if ($userField->type === UserFieldTypeEnum::BOOLEAN->value) {
            $this->userFieldService->setupBooleanValues();
        }
    }
}
