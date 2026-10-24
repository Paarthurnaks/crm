<?php

namespace Tests\Feature;

use App\Http\Filters\DealFilter;
use App\Http\Filters\UserFieldFilter;
use App\Models\Deal;
use App\Models\UserField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class QueryFilterCombinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_filter_and_sorting_are_applied_together(): void
    {
        $filter = new UserFieldFilter(Request::create('/', 'GET', [
            'entity' => 'deals',
            'order_by' => 'name',
            'order_direction' => 'desc',
        ]));

        $query = UserField::query();
        $filter->apply($query);

        $grammar = $query->getQuery()->getGrammar();
        $this->assertStringContainsString($grammar->wrap('entity').' = ?', $query->toSql());
        $this->assertStringContainsString($grammar->wrap('user_fields.name').' desc', $query->toSql());
        $this->assertStringContainsString($grammar->wrap('user_fields.id').' desc', $query->toSql());
        $this->assertContains('deals', $query->getBindings());
    }

    public function test_custom_field_filter_and_sorting_are_applied_together(): void
    {
        UserField::create(['name' => 'Reference', 'entity' => 'deals', 'type' => 'string', 'code' => 'reference']);

        $filter = new DealFilter(Request::create('/', 'GET', [
            'reference' => 'A-1',
            'order_by' => 'reference',
            'order_direction' => 'asc',
        ]));

        $query = Deal::query();
        $filter->apply($query);

        $grammar = $query->getQuery()->getGrammar();
        $this->assertStringContainsString($grammar->wrap('deals.id').' in (select', $query->toSql());
        $this->assertStringContainsString('left join '.$grammar->wrapTable('user_field_values'), $query->toSql());
        $this->assertStringContainsString($grammar->wrap('user_field_values.value').' asc', $query->toSql());
        $this->assertContains('A-1', $query->getBindings());
    }

    public function test_invalid_sorting_falls_back_to_stable_id_order(): void
    {
        $filter = new UserFieldFilter(Request::create('/', 'GET', [
            'entity' => 'deals',
            'order_by' => 'nonexistent_column',
            'order_direction' => 'sideways',
        ]));

        $query = UserField::query();
        $filter->apply($query);

        $grammar = $query->getQuery()->getGrammar();
        $this->assertStringContainsString($grammar->wrap('entity').' = ?', $query->toSql());
        $this->assertStringNotContainsString('nonexistent_column', $query->toSql());
        $this->assertStringContainsString($grammar->wrap('user_fields.id').' desc', $query->toSql());
    }
}
