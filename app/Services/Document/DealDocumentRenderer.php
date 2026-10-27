<?php

namespace App\Services\Document;

use App\Enums\EntityTypeEnum;
use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

class DealDocumentRenderer
{
    private const TEMP_FILE_TOKEN_LENGTH = 15;

    public function __construct(private readonly DocxToPdfConverter $converter) {}

    /** @return array{docx: string, pdf: string} */
    public function render(Deal $deal, DocumentTemplate $template): array
    {
        $startedAt = microtime(true);
        Log::debug('document.generation.started', ['deal_id' => $deal->id, 'template_id' => $template->id]);
        $media = $template->getMedia()->last();

        if (! $media || ! is_file($media->getPath())) {
            Log::error('Файл шаблона документа отсутствует', ['deal_id' => $deal->id, 'template_id' => $template->id]);
            throw new RuntimeException('Файл шаблона не найден');
        }

        $fileBaseName = Str::random(self::TEMP_FILE_TOKEN_LENGTH);
        $docxPath = storage_path($fileBaseName.'.docx');
        $pdfPath = storage_path($fileBaseName.'.pdf');
        $stage = 'values';

        try {
            $prepared = $this->prepareTemplateValues($deal);
            $this->logProgress('document.values.prepared', $deal, $template, $startedAt);

            $stage = 'template';
            $processor = $this->fillTemplate($media->getPath(), $prepared);
            $processor->saveAs($docxPath);
            $this->logProgress('document.template.saved', $deal, $template, $startedAt);

            $stage = 'conversion';
            Log::debug('document.converter.selected', ['converter' => $this->converter::class]);
            $pdfPath = $this->converter->convert($docxPath);
            $this->logProgress('document.prepared', $deal, $template, $startedAt);

            return ['docx' => $docxPath, 'pdf' => $pdfPath];
        } catch (\Throwable $exception) {
            File::delete([$docxPath, $pdfPath]);
            Log::error('document.generation.failed', [
                'deal_id' => $deal->id,
                'template_id' => $template->id,
                'stage' => $stage,
                'error_type' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function logProgress(string $event, Deal $deal, DocumentTemplate $template, float $startedAt): void
    {
        Log::debug($event, [
            'deal_id' => $deal->id,
            'template_id' => $template->id,
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);
    }

    /** @return array{values: array<string, string>, tableRows: ?array} */
    private function prepareTemplateValues(Deal $deal): array
    {
        $formatter = new TemplateValueFormatter;
        $userFieldValues = $this->userFieldValues($deal, $formatter);
        $tableRows = $formatter->tableRows($userFieldValues['table'] ?? null);
        unset($userFieldValues['table']);

        $values = array_merge($userFieldValues, $this->dealValues($deal));

        return ['values' => array_map($formatter->placeholder(...), $values), 'tableRows' => $tableRows];
    }

    /** @param array{values: array<string, string>, tableRows: ?array} $prepared */
    private function fillTemplate(string $templatePath, array $prepared): TemplateProcessor
    {
        $processor = new TemplateProcessor($templatePath);
        $processor->setValues($prepared['values']);

        if ($prepared['tableRows'] !== null) {
            $processor->cloneRowAndSetValues(array_key_first($prepared['tableRows'][0]), $prepared['tableRows']);
        }

        return $processor;
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
            'creator' => $this->userName($deal->creator),
            'responsible' => $this->userName($deal->responsible),
            'note' => $deal->note ?: 'Не указано',
            'amount' => $deal->amount ?? 0.00,
            'client' => $this->clientName($deal->client),
            'created_at' => $deal->created_at->toDateTimeString(),
            'updated_at' => $deal->updated_at->toDateTimeString(),
            'id' => $deal->id,
        ];
    }

    private function userName(?User $user): string
    {
        return $user ? "{$user->name} {$user->surname}" : 'Не указано';
    }

    private function clientName(User|string|null $client): string
    {
        return ($client instanceof User ? $this->userName($client) : $client) ?: 'Не указано';
    }
}
