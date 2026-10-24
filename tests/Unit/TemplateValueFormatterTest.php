<?php

namespace Tests\Unit;

use App\Services\Document\TemplateValueFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TemplateValueFormatterTest extends TestCase
{
    public function test_empty_boolean_and_zero_values_keep_their_meaning(): void
    {
        $formatter = new TemplateValueFormatter;

        $this->assertSame('Не указано', $formatter->userField('string', null));
        $this->assertSame('Не указано', $formatter->userField('string', ''));
        $this->assertSame('0', $formatter->userField('string', '0'));
        $this->assertSame('Да', $formatter->userField('boolean', '1'));
        $this->assertSame('Нет', $formatter->userField('boolean', '0'));
        $this->assertSame('Нет', $formatter->userField('boolean', 'false'));
    }

    public function test_table_rows_keep_column_names_and_render_scalar_values(): void
    {
        $formatter = new TemplateValueFormatter;

        $this->assertSame([
            ['name' => 'Первый', 'quantity' => '0'],
            ['name' => 'Второй', 'quantity' => '2'],
        ], $formatter->tableRows('[{"name":"Первый","quantity":0},{"name":"Второй","quantity":2}]'));
    }

    public function test_table_rows_reject_an_object_instead_of_a_row_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TemplateValueFormatter)->tableRows('{"name":"Первый"}');
    }
}
