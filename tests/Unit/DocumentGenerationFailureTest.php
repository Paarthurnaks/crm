<?php

namespace Tests\Unit;

use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Services\DocumentService;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DocumentGenerationFailureTest extends TestCase
{
    public function test_generation_rejects_template_without_file(): void
    {
        $deal = new Deal;
        $deal->id = 42;
        $template = Mockery::mock(DocumentTemplate::class)->makePartial();
        $template->id = 21;
        $template->shouldReceive('getMedia')->once()->andReturn(new MediaCollection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Файл шаблона не найден');

        (new DocumentService)->generateDocumentFromTemplate($deal, $template);
    }

    public function test_conversion_reports_unsuccessful_process(): void
    {
        $docxPath = tempnam(sys_get_temp_dir(), 'docx-');
        $service = new class extends DocumentService
        {
            protected function conversionProcess(array $command): Process
            {
                return new class($command) extends Process
                {
                    public function run(?callable $callback = null, array $env = []): int
                    {
                        return 1;
                    }

                    public function isSuccessful(): bool
                    {
                        return false;
                    }

                    public function getExitCode(): ?int
                    {
                        return 1;
                    }
                };
            }
        };

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Не удалось преобразовать документ в PDF');

            $service->convertDocxToPdf($docxPath);
        } finally {
            File::delete($docxPath);
        }
    }
}
