<?php

namespace App\Services;

use App\Enums\EntityTypeEnum;
use App\Http\Filters\DocumentTemplateFilter;
use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\UserField;
use App\Services\Document\DocxToPdfConverter;
use App\Services\Document\LibreOfficeDocxToPdfConverter;
use App\Services\Document\TemplateValueFormatter;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class DocumentService
{
    /** @return array{docx: Media, pdf: Media} */
    public function generateDealDocument(Deal $deal, DocumentTemplate $template, string $title, User $actor): array
    {
        $document = $this->generateDocumentFromTemplate($deal, $template);
        $docxMedia = null;

        try {
            $properties = ['user_id' => $actor->id, 'template_id' => $template->id];
            $docxMedia = $deal->addMedia($document['docx'])
                ->setName($title)
                ->setFileName($title.'.docx')
                ->withCustomProperties($properties)
                ->toMediaCollection('docs');

            $pdfMedia = $deal->addMedia($document['pdf'])
                ->setName($title)
                ->setFileName($title.'.pdf')
                ->withCustomProperties($properties)
                ->toMediaCollection('docs');
        } catch (\Throwable $exception) {
            try {
                $docxMedia?->delete();
            } catch (\Throwable $cleanupException) {
                Log::error('document.media.cleanup.failed', ['deal_id' => $deal->id, 'exception' => $cleanupException::class]);
            }
            Log::error('document.media.save.failed', ['deal_id' => $deal->id, 'template_id' => $template->id, 'exception' => $exception::class]);
            throw $exception;
        } finally {
            File::delete([$document['docx'], $document['pdf']]);
        }

        Log::info('document.media.saved', ['deal_id' => $deal->id, 'template_id' => $template->id, 'docx_media_id' => $docxMedia->id, 'pdf_media_id' => $pdfMedia->id]);

        return ['docx' => $docxMedia, 'pdf' => $pdfMedia];
    }

    private readonly DocxToPdfConverter $converter;

    public function __construct(?DocxToPdfConverter $converter = null)
    {
        $this->converter = $converter ?? new LibreOfficeDocxToPdfConverter;
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
        $startedAt = microtime(true);
        $formatter = new TemplateValueFormatter;
        $media = $template->getMedia()->last();

        if (! $media || ! is_file($media->getPath())) {
            Log::error('Файл шаблона документа отсутствует', ['deal_id' => $deal->id, 'template_id' => $template->id]);

            throw new RuntimeException('Файл шаблона не найден');
        }

        try {
            $userFieldValues = $this->userFieldValues($deal, $formatter);
            $tableRows = $formatter->tableRows($userFieldValues['table'] ?? null);
            unset($userFieldValues['table']);

            $values = array_merge($userFieldValues, $this->dealValues($deal));
            $values = array_map($formatter->placeholder(...), $values);
        } catch (\Throwable $exception) {
            Log::error('Ошибка подготовки данных для шаблона документа', [
                'deal_id' => $deal->id,
                'template_id' => $template->id,
                'error_type' => get_class($exception),
            ]);

            throw $exception;
        }

        Log::debug('Данные для шаблона документа подготовлены', [
            'deal_id' => $deal->id,
            'template_id' => $template->id,
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        try {
            $templateProcessor = new TemplateProcessor($media->getPath());
            $templateProcessor->setValues($values);

            if ($tableRows !== null) {
                $this->fillTable($templateProcessor, $tableRows);
            }
        } catch (\Throwable $exception) {
            Log::error('Ошибка заполнения шаблона документа', [
                'deal_id' => $deal->id,
                'template_id' => $template->id,
                'error_type' => get_class($exception),
            ]);

            throw $exception;
        }

        $fileBaseName = Str::random(15);
        $fileDocxName = storage_path($fileBaseName.'.docx');
        $filePdfName = storage_path($fileBaseName.'.pdf');

        try {
            $templateProcessor->saveAs($fileDocxName);
            Log::debug('Шаблон документа заполнен', [
                'deal_id' => $deal->id,
                'template_id' => $template->id,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            $filePdfName = $this->convertDocxToPdf($fileDocxName);

            Log::debug('Документ подготовлен', [
                'deal_id' => $deal->id,
                'template_id' => $template->id,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return ['docx' => $fileDocxName, 'pdf' => $filePdfName];
        } catch (\Throwable $exception) {
            File::delete([$fileDocxName, $filePdfName]);
            Log::error('Ошибка подготовки документа', [
                'deal_id' => $deal->id,
                'template_id' => $template->id,
                'error_type' => get_class($exception),
            ]);

            throw $exception;
        }
    }

    private function userFieldValues(Deal $deal, TemplateValueFormatter $formatter): array
    {
        return UserField::with(['fieldValues' => function ($query) use ($deal) {
            $query->where('entity_id', $deal->id);
        }])
            ->where('entity', EntityTypeEnum::DEALS->value)
            ->get()
            ->mapWithKeys(function (UserField $field) use ($formatter) {
                $value = $field->fieldValues->first()?->value;

                return [$field->code => $field->code === 'table'
                    ? $value
                    : $formatter->userField($field->type, $value)];
            })
            ->toArray();
    }

    private function dealValues(Deal $deal): array
    {
        return [
            'title' => $deal->title,
            'sales_stage' => $deal->salesStage?->title ?? 'Не указано',
            'sales_funnel' => $deal->salesFunnel?->title ?? 'Не указано',
            'creator' => $deal->creator ? "{$deal->creator->name} {$deal->creator->surname}" : 'Не указано',
            'responsible' => $deal->responsible ? "{$deal->responsible->name} {$deal->responsible->surname}" : 'Не указано',
            'note' => $deal->note ?: 'Не указано',
            'amount' => $deal->amount ?? 0.00,
            'client' => ($deal->client instanceof User ? "{$deal->client?->name} {$deal->client?->surname}" : $deal->client) ?: 'Не указано',
            'created_at' => $deal->created_at->toDateTimeString(),
            'updated_at' => $deal->updated_at->toDateTimeString(),
            'id' => $deal->id,
        ];
    }

    private function fillTable(TemplateProcessor $templateProcessor, array $rows): void
    {
        $templateProcessor->cloneRowAndSetValues(array_key_first($rows[0]), $rows);
    }

    public function convertDocxToPdf(string $docxPath): string
    {
        Log::debug('document.converter.selected', ['converter' => $this->converter::class]);

        return $this->converter->convert($docxPath);
    }
}
