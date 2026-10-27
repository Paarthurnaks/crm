<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\Document\DocxToPdfConverter;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use PhpOffice\PhpWord\PhpWord;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;
use ZipArchive;

class DocumentGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_endpoint_returns_not_found_for_missing_deal(): void
    {
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Тест',
            'surname' => 'Пользователь',
            'email' => 'document-contract@example.test',
            'password' => 'unused',
        ]));

        $this->actingAs($user, 'api')
            ->postJson('/api/deals/999999/generateDocument/999999', ['title' => 'Документ'])
            ->assertNotFound();
    }

    public function test_generation_returns_filled_docx_and_pdf_paths(): void
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'template-');
        $templatePath = $temporaryPath.'.docx';
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('${title}');
        $section->addText('${client}');
        $word->save($templatePath, 'Word2007');

        $media = Mockery::mock(Media::class)->makePartial();
        $media->shouldReceive('getPath')->andReturn($templatePath);
        $template = Mockery::mock(DocumentTemplate::class)->makePartial();
        $template->id = 21;
        $template->shouldReceive('getMedia')->andReturn(new MediaCollection([$media]));

        $deal = new Deal;
        $deal->id = 42;
        $deal->title = 'Проверка документа';
        $deal->client = 'Тестовый клиент';
        $deal->created_at = now();
        $deal->updated_at = now();

        $converter = new class implements DocxToPdfConverter
        {
            public function convert(string $docxPath): string
            {
                $pdfPath = dirname($docxPath).DIRECTORY_SEPARATOR.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';
                File::put($pdfPath, 'PDF fixture');

                return $pdfPath;
            }
        };
        $service = new DocumentService($converter);

        $result = null;

        try {
            $result = $service->generateDocumentFromTemplate($deal, $template);

            $this->assertSame(['docx', 'pdf'], array_keys($result));
            $this->assertFileExists($result['docx']);
            $this->assertFileExists($result['pdf']);
            $this->assertSame('docx', pathinfo($result['docx'], PATHINFO_EXTENSION));
            $this->assertSame('pdf', pathinfo($result['pdf'], PATHINFO_EXTENSION));
            $this->assertSame('PDF fixture', File::get($result['pdf']));

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($result['docx']));

            try {
                $documentXml = $zip->getFromName('word/document.xml');
                $this->assertIsString($documentXml);
                $this->assertStringContainsString('Проверка документа', $documentXml);
                $this->assertStringContainsString('Тестовый клиент', $documentXml);
                $this->assertStringNotContainsString('${title}', $documentXml);
            } finally {
                $zip->close();
            }
        } finally {
            File::delete(array_filter([$temporaryPath, $templatePath, $result['docx'] ?? null, $result['pdf'] ?? null]));
        }
    }
}
