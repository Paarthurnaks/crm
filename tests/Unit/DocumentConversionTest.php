<?php

namespace Tests\Unit;

use App\Services\DocumentService;
use RuntimeException;
use Tests\TestCase;

class DocumentConversionTest extends TestCase
{
    public function test_conversion_rejects_missing_docx_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Файл DOCX не найден');

        (new DocumentService)->convertDocxToPdf(sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-'.bin2hex(random_bytes(8)).'.docx');
    }
}
