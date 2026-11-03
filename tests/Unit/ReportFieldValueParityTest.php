<?php

namespace Tests\Unit;

use App\Exports\ReportExport;
use App\Models\Deal;
use App\Models\Field;
use App\Models\Report;
use App\Models\ReportField;
use App\Models\User;
use App\Services\ReportFieldService;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportFieldValueParityTest extends TestCase
{
    public function test_view_and_export_keep_their_existing_representation_of_user_and_scalar_fields(): void
    {
        $responsible = new User(['name' => 'Иван', 'surname' => 'Петров']);
        $responsible->setRelation('highLevelAdmin', null);
        $deal = new Deal(['title' => 'Сделка']);
        $deal->setRelation('responsible', $responsible);

        $titleField = $this->reportField('Название', 'title');
        $responsibleField = $this->reportField('Ответственный', 'responsible');
        $report = new Report;
        $report->setRelation('fields', new Collection([$titleField, $responsibleField]));
        $service = new ReportService(Mockery::mock(ReportFieldService::class));

        $service->setReportFieldsValues($report, $deal);
        $rows = $service->exportRows($report, new Collection([$deal]));

        $this->assertSame('Сделка', $titleField->value);
        $this->assertSame('Иван Петров', $responsibleField->value);
        $this->assertSame('Сделка', $rows[0]['Название']);
        $this->assertSame('Иван', json_decode($rows[0]['Ответственный'], true)['name']);
    }

    public function test_xlsx_contains_report_headings_and_values(): void
    {
        $content = Excel::raw(new ReportExport([['Название' => 'Сделка']]), \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'crm-report-');

        try {
            file_put_contents($path, $content);
            $rows = IOFactory::load($path)->getActiveSheet()->toArray();

            $this->assertSame('Название', $rows[0][0]);
            $this->assertSame('Сделка', $rows[1][0]);
        } finally {
            unlink($path);
        }
    }

    private function reportField(string $name, string $code): ReportField
    {
        $reportField = new ReportField;
        $reportField->setRelation('field', new Field(['name' => $name, 'code' => $code]));
        $reportField->setRelation('userField', null);

        return $reportField;
    }
}
