<?php

namespace App\Services;

use App\Models\ReportField;

class ReportFieldService
{
    public function createReportField(array $reportFieldData, int $reportId): ReportField
    {
        return ReportField::create([
            'report_id' => $reportId,
            'field_id' => $reportFieldData['field_id'] ?? null,
            'user_field_id' => $reportFieldData['user_field_id'] ?? null,
            'position' => $reportFieldData['position'],
        ]);
    }

    public function updateReportField(ReportField $reportField, array $reportFieldData): void
    {
        unset($reportFieldData['id']); // id не обновляем, поэтому убираем из объекта
        $reportField->update($reportFieldData);
    }
}
