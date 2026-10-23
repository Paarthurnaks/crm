<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportExport implements FromArray, WithHeadings, ShouldAutoSize
{

    protected array $reports;

    public function __construct($reports)
    {
        $this->reports = $reports;
    }

    public function array(): array
    {
        return $this->reports;
    }

    public function headings(): array
    {
        return array_keys($this->reports[0]);
    }
}
