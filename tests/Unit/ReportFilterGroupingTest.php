<?php

namespace Tests\Unit;

use App\Models\Deal;
use App\Models\Field;
use App\Models\Report;
use App\Models\ReportField;
use App\Services\ReportFieldService;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class ReportFilterGroupingTest extends TestCase
{
    public function test_nested_all_and_any_conditions_keep_their_bindings(): void
    {
        $query = Deal::query();
        $service = new ReportService(Mockery::mock(ReportFieldService::class));

        $service->filterQuery($query, [[
            'condition' => 'Все',
            'value' => [
                ['key' => 'responsible', 'condition' => 'Равно', 'value' => 7],
                ['condition' => 'Любой', 'value' => [
                    ['key' => 'amount', 'condition' => 'Больше', 'value' => 100],
                    ['key' => 'title', 'condition' => 'Начинается с', 'value' => 'План'],
                ]],
            ],
        ]]);

        $this->assertSame([7, 100, 'План%'], $query->getBindings());
        $this->assertStringContainsString('responsible_id', $query->toSql());
        $this->assertStringContainsString(' or ', $query->toSql());
    }

    public function test_export_rows_use_loaded_visible_fields(): void
    {
        $field = new Field(['name' => 'Название', 'code' => 'title']);
        $reportField = new ReportField;
        $reportField->setRelation('field', $field);
        $reportField->setRelation('userField', null);
        $report = new Report;
        $report->setRelation('fields', new Collection([$reportField]));
        $deals = new Collection([new Deal(['title' => 'Сделка'])]);

        $rows = (new ReportService(Mockery::mock(ReportFieldService::class)))->exportRows($report, $deals);

        $this->assertSame([['Название' => 'Сделка']], $rows);
    }

    public function test_empty_export_has_no_rows(): void
    {
        $report = new Report;
        $report->setRelation('fields', new Collection);

        $rows = (new ReportService(Mockery::mock(ReportFieldService::class)))
            ->exportRows($report, new Collection);

        $this->assertSame([], $rows);
    }
}
