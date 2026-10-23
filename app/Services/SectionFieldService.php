<?php

namespace App\Services;

use App\Models\SectionField;
use App\Models\UserField;
use App\Models\UserFieldValue;

class SectionFieldService
{   
    /**
     * @param array
     * @param int
     * @return SectionField
     */
    public function createSectionField(array $sectionFieldData, int $sectionId): SectionField
    {
        $sectionField = SectionField::create([
            "section_id" => $sectionId,
            "field_id" => $sectionFieldData["field_id"] ?? null,
            "user_field_id" => $sectionFieldData["user_field_id"] ?? null,
            "position" => $sectionFieldData["position"]
        ]);

        return $sectionField;
    }

    /**
     * @param array
     * @return void
     */
    public function updateSectionField(SectionField $sectionField, array $sectionfieldData): void
    {
        unset($sectionfieldData["id"]); // id не обновляем, поэтому убираем из объекта
        $sectionField->update($sectionfieldData);
    }
}