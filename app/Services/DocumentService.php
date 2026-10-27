<?php

namespace App\Services;

use App\Http\Filters\DocumentTemplateFilter;
use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\Document\DealDocumentRenderer;
use App\Services\Document\DocxToPdfConverter;
use App\Services\Document\LibreOfficeDocxToPdfConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class DocumentService
{
    private readonly DocxToPdfConverter $converter;

    public function __construct(?DocxToPdfConverter $converter = null)
    {
        $this->converter = $converter ?? new LibreOfficeDocxToPdfConverter;
    }

    /** @return array{docx: Media, pdf: Media} */
    public function generateDealDocument(Deal $deal, DocumentTemplate $template, string $title, User $actor): array
    {
        Log::debug('document.media.save.started', [
            'deal_id' => $deal->id,
            'template_id' => $template->id,
            'actor_id' => $actor->id,
        ]);
        $document = $this->generateDocumentFromTemplate($deal, $template);

        try {
            $properties = ['user_id' => $actor->id, 'template_id' => $template->id];
            $media = $this->saveGeneratedMedia($deal, $document, $title, $properties);
            Log::info('document.media.saved', [
                'deal_id' => $deal->id,
                'template_id' => $template->id,
                'docx_media_id' => $media['docx']->id,
                'pdf_media_id' => $media['pdf']->id,
            ]);

            return $media;
        } finally {
            File::delete([$document['docx'], $document['pdf']]);
        }
    }

    /**
     * @param  array{docx: string, pdf: string}  $document
     * @param  array{user_id: int, template_id: int}  $properties
     * @return array{docx: Media, pdf: Media}
     */
    private function saveGeneratedMedia(Deal $deal, array $document, string $title, array $properties): array
    {
        $saved = [];

        try {
            foreach (['docx', 'pdf'] as $format) {
                $saved[$format] = $deal->addMedia($document[$format])
                    ->setName($title)
                    ->setFileName($title.'.'.$format)
                    ->withCustomProperties($properties)
                    ->toMediaCollection('docs');
            }
        } catch (\Throwable $exception) {
            try {
                ($saved['docx'] ?? null)?->delete();
            } catch (\Throwable $cleanupException) {
                Log::error('document.media.cleanup.failed', [
                    'deal_id' => $deal->id,
                    'exception' => $cleanupException::class,
                ]);
            }
            Log::error('document.media.save.failed', ['deal_id' => $deal->id, 'exception' => $exception::class]);
            throw $exception;
        }

        return $saved;
    }

    public function create(array $data, User $actor, UploadedFile $upload): DocumentTemplate
    {
        $documentTemplate = DocumentTemplate::create([
            'creator_id' => $actor->id,
            'entity' => $data['entity'],
            'title' => $data['title'],
        ]);

        $documentTemplate->addMedia($upload)->toMediaCollection();

        return $documentTemplate;
    }

    /**
     * @throws NotFoundResourceException
     */
    public function update(int $templateId, array $data): DocumentTemplate
    {
        $documentTemplate = DocumentTemplate::find($templateId);

        if (! $documentTemplate) {
            throw new NotFoundResourceException(__('document template not found'));
        }

        unset($data['template']);

        foreach ($data as $key => $value) {
            $documentTemplate->{$key} = $value;
        }

        $documentTemplate->save();

        return $documentTemplate;
    }

    public function updateTemplate(int $templateId, UploadedFile $upload): DocumentTemplate
    {
        $documentTemplate = DocumentTemplate::find($templateId);

        if (! $documentTemplate) {
            throw new NotFoundResourceException(__('document template not found'));
        }

        $documentTemplate->addMedia($upload)->toMediaCollection();

        return $documentTemplate;
    }

    public function getList(DocumentTemplateFilter $filter, array $queryParams = []): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        return DocumentTemplate::filter($filter)
            ->paginate($limit);
    }

    public function delete(DocumentTemplate $template): ?bool
    {
        return $template->delete();
    }

    /**
     * @throws \PhpOffice\PhpWord\Exception\Exception
     * @throws RuntimeException
     */
    public function generateDocumentFromTemplate(Deal $deal, DocumentTemplate $template): array
    {
        return (new DealDocumentRenderer($this->converter))->render($deal, $template);
    }

    public function convertDocxToPdf(string $docxPath): string
    {
        Log::debug('document.converter.selected', ['converter' => $this->converter::class]);

        return $this->converter->convert($docxPath);
    }
}
