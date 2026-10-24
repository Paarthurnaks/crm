<?php

namespace App\Services;

use App\Enums\UserFieldTypeEnum;
use App\Http\Filters\DocumentTemplateFilter;
use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
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
        // берём все юзерфилды и их значение у этой сделки, если ее нет - вернется пустой массив,
        // а в доке будет "Не указано"
        $userFieldValues = UserField::with(['fieldValues' => function ($query) use ($deal) {
            $query->where('entity_id', $deal->id);
        }])
            ->get()
            ->mapWithKeys(function ($userField) {
                $userFieldValue = $userField->fieldValues->first();

                if ($userField->type === UserFieldTypeEnum::BOOLEAN->value) {
                    return [$userField->code => $userFieldValue?->value ? 'Да' : 'Нет'];
                }

                return [$userField->code => $userFieldValue?->value ?: 'Не указано'];
            })
            ->toArray();

        $data = [
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

        $media = $template->getMedia()->last();
        if (! $media || ! is_file($media->getPath())) {
            Log::warning('Document template file missing', ['template_id' => $template->id]);

            throw new RuntimeException('Файл шаблона не найден');
        }

        $path = $media->getPath();
        $templateProcessor = new TemplateProcessor($path);

        $tableData = $userFieldValues['table'] ?? null;
        unset($userFieldValues['table']);
        $data = array_merge($userFieldValues, $data);
        $data = array_map(function ($value) {
            if (is_null($value)) {
                return '';
            }
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            return (string) $value;
        }, $data);
        $templateProcessor->setValues($data);

        if ($tableData !== null) {
            if (is_string($tableData)) {
                $tableData = json_decode($tableData, true);
            }

            if (! is_array($tableData) || $tableData === [] || ! is_string(array_key_first($tableData))) {
                throw new InvalidArgumentException('Некорректные данные таблицы в шаблоне');
            }

            $this->fillTable($templateProcessor, $tableData);
        }

        $fileBaseName = Str::random(15);
        $fileDocxName = storage_path($fileBaseName.'.docx');

        $templateProcessor->saveAs($fileDocxName);

        try {
            $filePdfName = $this->convertDocxToPdf($fileDocxName);
        } catch (\Throwable $exception) {
            File::delete($fileDocxName);

            throw $exception;
        }

        return [
            'docx' => $fileDocxName,
            'pdf' => $filePdfName,
        ];
    }

    private function fillTable(TemplateProcessor $templateProcessor, array $tableData): void
    {
        $templateProcessor->cloneRowAndSetValues(current(array_keys($tableData)), $tableData);
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
            $process = new Process([
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
}
