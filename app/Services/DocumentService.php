<?php

namespace App\Services;

use App\Enums\EntityTypeEnum;
use App\Http\Filters\DocumentTemplateFilter;
use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\UserField;
use App\Services\Document\TemplateValueFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class DocumentService
{
    public function create(array $data): mixed
    {
        $user = Auth::user();

        $documentTemplate = DocumentTemplate::create([
            'creator_id' => $user->id,
            'entity' => $data['entity'],
            'title' => $data['title'],
        ]);

        $documentTemplate->addMediaFromRequest('template')->toMediaCollection();

        return $documentTemplate;
    }

    /**
     * @throws Exception
     */
    public function update(int $templateId, array $data)
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

    public function updateTemplate(int $templateId)
    {
        $documentTemplate = DocumentTemplate::find($templateId);

        if (! $documentTemplate) {
            throw new NotFoundResourceException(__('document template not found'));
        }

        if (request()->has('template')) {
            $documentTemplate->addMediaFromRequest('template')->toMediaCollection();
        }

        return $documentTemplate;
    }

    public function getList(DocumentTemplateFilter $filter, array $queryParams = [])
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
     * @throws CopyFileException
     * @throws CreateTemporaryFileException|\PhpOffice\PhpWord\Exception\Exception
     * @throws MpdfException
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
        if (! is_file($docxPath)) {
            throw new RuntimeException('Файл DOCX не найден');
        }

        $outputDir = dirname($docxPath);
        $outputPdf = $outputDir.DIRECTORY_SEPARATOR.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';
        $profilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lo_profile_'.Str::random(16);

        if (! File::makeDirectory($profilePath, 0700, true)) {
            throw new RuntimeException('Не удалось создать профиль конвертера');
        }

        try {
            $profileUrl = 'file:///'.str_replace('\\', '/', ltrim($profilePath, '\\/'));
            $process = $this->conversionProcess([
                'libreoffice',
                '-env:UserInstallation='.$profileUrl,
                '--headless',
                '--convert-to',
                'pdf',
                '--outdir',
                $outputDir,
                $docxPath,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPdf)) {
                Log::error('Document conversion failed', ['exit_code' => $process->getExitCode()]);

                throw new RuntimeException('Не удалось преобразовать документ в PDF');
            }

            Log::info('Document converted', ['file_id' => pathinfo($docxPath, PATHINFO_FILENAME)]);

            return $outputPdf;
        } finally {
            File::deleteDirectory($profilePath);
        }
    }

    protected function conversionProcess(array $command): Process
    {
        return new Process($command);
    }
}
