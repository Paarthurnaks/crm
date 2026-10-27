<?php

namespace Tests\Unit;

use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\Document\LibreOfficeDocxToPdfConverter;
use App\Services\DocumentService;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DocumentGenerationFailureTest extends TestCase
{
    public function test_second_media_failure_removes_first_media_and_temporary_files(): void
    {
        $docxPath = tempnam(sys_get_temp_dir(), 'docx-');
        $pdfPath = tempnam(sys_get_temp_dir(), 'pdf-');
        $firstMedia = Mockery::mock(Media::class)->makePartial();
        $firstMedia->shouldReceive('delete')->once();

        $adder = Mockery::mock(FileAdder::class);
        $adder->shouldReceive('setName')->once()->andReturnSelf();
        $adder->shouldReceive('setFileName')->once()->andReturnSelf();
        $adder->shouldReceive('withCustomProperties')->once()->andReturnSelf();
        $adder->shouldReceive('toMediaCollection')->once()->with('docs')->andReturn($firstMedia);

        $deal = Mockery::mock(Deal::class)->makePartial();
        $deal->id = 42;
        $callCount = 0;
        $deal->shouldReceive('addMedia')->twice()->andReturnUsing(function () use ($adder, &$callCount) {
            if (++$callCount === 2) {
                throw new RuntimeException('second media failed');
            }

            return $adder;
        });
        $template = new DocumentTemplate;
        $template->id = 21;
        $actor = new User;
        $actor->id = 7;
        $service = Mockery::mock(DocumentService::class, [null])->makePartial();
        $service->shouldReceive('generateDocumentFromTemplate')->once()->andReturn([
            'docx' => $docxPath,
            'pdf' => $pdfPath,
        ]);

        try {
            $service->generateDealDocument($deal, $template, 'Документ', $actor);
            $this->fail('Ожидался отказ сохранения PDF');
        } catch (RuntimeException) {
            $this->assertFileDoesNotExist($docxPath);
            $this->assertFileDoesNotExist($pdfPath);
        } finally {
            File::delete([$docxPath, $pdfPath]);
        }
    }

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
        $converter = new class extends LibreOfficeDocxToPdfConverter
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

            $converter->convert($docxPath);
        } finally {
            File::delete($docxPath);
        }
    }
}
