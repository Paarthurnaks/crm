<?php

namespace Tests\Unit;

use App\Models\Deal;
use App\Services\ReportFieldService;
use App\Services\ReportService;
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
}
