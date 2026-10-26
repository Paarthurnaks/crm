<?php

namespace App\Services\Document;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class LibreOfficeDocxToPdfConverter implements DocxToPdfConverter
{
    public function convert(string $docxPath): string
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException('Файл DOCX не найден');
        }

        $outputDir = dirname($docxPath);
        $outputPdf = $outputDir.DIRECTORY_SEPARATOR.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';
        $profilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lo_profile_'.Str::random(16);

        if (! File::makeDirectory($profilePath, 0700, true)) {
            Log::error('document.conversion.profile_failed');
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
                Log::error('document.conversion.failed', ['exit_code' => $process->getExitCode(), 'output_exists' => is_file($outputPdf)]);
                throw new RuntimeException('Не удалось преобразовать документ в PDF');
            }

            Log::info('document.conversion.completed', ['exit_code' => $process->getExitCode()]);

            return $outputPdf;
        } catch (\Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                Log::error('document.conversion.error', ['exception' => $exception::class]);
            }

            throw $exception;
        } finally {
            File::deleteDirectory($profilePath);
        }
    }

    protected function conversionProcess(array $command): Process
    {
        return new Process($command);
    }
}
