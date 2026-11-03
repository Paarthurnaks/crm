<?php

namespace Tests\Feature;

use App\Exports\ReportExport;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportExcelCompatibilityTest extends TestCase
{
    public function test_report_export_produces_readable_xlsx_with_headings_and_values(): void
    {
        $contents = ExcelFacade::raw(new ReportExport([
            ['name' => 'Deal', 'amount' => 125],
        ]), Excel::XLSX);

        $path = tempnam(sys_get_temp_dir(), 'report-export-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $contents);
            $sheet = IOFactory::load($path)->getActiveSheet();
            $rows = $sheet->rangeToArray('A1:B2');

            $this->assertSame(['name', 'amount'], $rows[0]);
            $this->assertSame('Deal', $rows[1][0]);
            $this->assertSame(125, $sheet->getCell('B2')->getValue());
        } finally {
            unlink($path);
        }
    }
}
