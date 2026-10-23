<?php

namespace App\Services;

use App\Enums\UserFieldTypeEnum;
use App\Http\Filters\DocumentTemplateFilter;
use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\UserField;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class DocumentService
{
    /**
     * @param array $data
     * @return mixed
     */
    public function create(array $data): mixed
    {
        $user = Auth::user();

        $documentTemplate = DocumentTemplate::create([
            'creator_id' => $user->id,
            'entity' => $data['entity'],
            'title' => $data['title']
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

        if (!$documentTemplate) {
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

        if (!$documentTemplate) {
            throw new NotFoundResourceException(__('document template not found'));
        }

        if (request()->has('template')) {
            $documentTemplate->addMediaFromRequest('template')->toMediaCollection();
        }

        return $documentTemplate;
    }

    public function getList(DocumentTemplateFilter $filter, array $queryParams = [])
    {
        $limit = $queryParams["limit"] ?? config("constants.paginator.limit");

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

                if($userField->type === UserFieldTypeEnum::BOOLEAN->value) {
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
            'client' => ($deal->client instanceof User ?  "{$deal->client?->name} {$deal->client?->surname}" : $deal->client) ?: 'Не указано',
            'created_at' => $deal->created_at->toDateTimeString(),
            'updated_at' => $deal->updated_at->toDateTimeString(),
            'id' => $deal->id,
        ];

        $path = $template->getMedia()->last()->getPath();
        $templateProcessor = new TemplateProcessor($path);

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


        if (isset($data['table'])) {
            $tableData = $data['table'];
            $this->fillTable($templateProcessor, $tableData);
        }

        $fileBaseName = Str::random(15);
        $fileDocxName = storage_path($fileBaseName . '.docx');
//        $filePdfName = storage_path($fileBaseName . '.pdf');

        $templateProcessor->saveAs($fileDocxName);

        // конвертация из docx в pdf, формирование html для нормализации utf-8 кодировки
        // формирование pdf и удаление html
//        Settings::setPdfRenderer(Settings::PDF_RENDERER_DOMPDF, base_path('vendor/dompdf/dompdf'));
        $phpWord = IOFactory::load($fileDocxName);
//
//        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
//        $htmlWriter->save(storage_path($fileBaseName . '.html'));

//        $options = new \Dompdf\Options();
//        $options->set('defaultFont', 'DejaVu Sans');
//        $options->set('isHtml5ParserEnabled', true);
//        $options->set('isPhpEnabled', true);
//        $options->setIsRemoteEnabled(true);

//        $dompdf = new \Dompdf\Dompdf($options);
//        $html = '
//<!DOCTYPE html>
//<html>
//<head>
//    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
//    <style>
//        body { font-family: DejaVu Sans, sans-serif; line-height: 1.6; }
//        table { border-collapse: collapse; width: 100%; }
//        table, th, td { border: 1px solid #ddd; }
//        th, td { padding: 8px; text-align: left; }
//    </style>
//</head>
//<body>' . file_get_contents(storage_path($fileBaseName . '.html')) . '
//</body>
//</html>';
//        $dompdf->loadHtml($html, 'UTF-8');
//        $dompdf->setPaper('A4');
//        $dompdf->render();

//        $output = $dompdf->output();
//        file_put_contents($filePdfName, $output);

//        unlink(storage_path($fileBaseName . '.html'));

        $filePdfName = $this->convertDocxToPdf($fileDocxName);
        return [
            'docx' => $fileDocxName,
            'pdf' => $filePdfName
        ];
    }

    private function fillTable(TemplateProcessor $templateProcessor, $tableData): void
    {
        $templateProcessor->cloneRowAndSetValues(current(array_keys($tableData)), $tableData);
    }

    public function convertDocxToPdf(string $docxPath): string {
        $outputPdf = storage_path() . '/' . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';

        $profilePath = '/tmp/lo_profile_' . uniqid();
        mkdir($profilePath, 0777, true);

        $command = sprintf(
            'HOME=%s libreoffice --headless --convert-to pdf --outdir "%s" "%s" 2>&1',
            $profilePath,
            storage_path(),
            $docxPath
        );

        exec($command, $output, $returnCode);

        Log::info('LibreOffice output', [
            'command' => $command,
            'return_code' => $returnCode,
            'output' => $output,
            'file_exists' => file_exists($docxPath)
        ]);
//
//        if ($returnCode !== 0) {
//            throw new Exception("Конвертация не удалась: " . implode("\n", $output));
//        }

        return $outputPdf;
    }
}
