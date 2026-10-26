<?php

namespace Tests\Unit;

use App\Services\Document\LibreOfficeDocxToPdfConverter;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LibreOfficeDocxToPdfConverterTest extends TestCase
{
    public function test_successful_process_returns_created_pdf(): void
    {
        $docxPath = tempnam(sys_get_temp_dir(), 'docx-');
        $pdfPath = dirname($docxPath).DIRECTORY_SEPARATOR.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';
        $converter = new class extends LibreOfficeDocxToPdfConverter
        {
            protected function conversionProcess(array $command): Process
            {
                return new class($command) extends Process
                {
                    private array $arguments;

                    public function __construct(array $command)
                    {
                        parent::__construct($command);
                        $this->arguments = $command;
                    }

                    public function run(?callable $callback = null, array $env = []): int
                    {
                        $docxPath = $this->arguments[count($this->arguments) - 1];
                        $pdfPath = dirname($docxPath).DIRECTORY_SEPARATOR.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';
                        File::put($pdfPath, 'PDF fixture');

                        return 0;
                    }

                    public function isSuccessful(): bool
                    {
                        return true;
                    }

                    public function getExitCode(): ?int
                    {
                        return 0;
                    }
                };
            }
        };

        try {
            $this->assertSame($pdfPath, $converter->convert($docxPath));
            $this->assertFileExists($pdfPath);
        } finally {
            File::delete([$docxPath, $pdfPath]);
        }
    }

    public function test_successful_process_without_output_is_rejected(): void
    {
        $docxPath = tempnam(sys_get_temp_dir(), 'docx-');
        $converter = new class extends LibreOfficeDocxToPdfConverter
        {
            protected function conversionProcess(array $command): Process
            {
                return new class($command) extends Process
                {
                    public function run(?callable $callback = null, array $env = []): int
                    {
                        return 0;
                    }

                    public function isSuccessful(): bool
                    {
                        return true;
                    }

                    public function getExitCode(): ?int
                    {
                        return 0;
                    }
                };
            }
        };

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Не удалось преобразовать документ в PDF');

            $converter->convert($docxPath);
        } finally {
            File::delete($docxPath);
        }
    }
}
