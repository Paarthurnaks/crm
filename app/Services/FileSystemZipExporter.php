<?php

namespace App\Services;

use App\Models\DiskEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

class FileSystemZipExporter
{
    private const FILE_NAME_LENGTH = 24;

    private const ZIP_CREATE_ERROR = 'Произошла ошибка при создании файла';

    public function export(string $path): string
    {
        $directory = $this->normalizePath($path);
        Log::debug('disk.export.started', ['path_hash' => hash('sha256', $directory)]);

        $entities = DiskEntity::query()
            ->where(fn (Builder $query) => $this->wherePathWithin($query, $directory))
            ->where('deleted', false)
            ->get();

        if ($entities->isEmpty()) {
            Log::warning('disk.export.empty', ['path_hash' => hash('sha256', $directory)]);
            throw new \Exception('Папка пустая');
        }

        $zipDirectory = storage_path('app/tmp/exports');
        File::ensureDirectoryExists($zipDirectory);
        $zipPath = $zipDirectory.DIRECTORY_SEPARATOR.Str::random(self::FILE_NAME_LENGTH).'.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            Log::error('disk.export.open_failed', ['path_hash' => hash('sha256', $directory)]);
            throw new RuntimeException(self::ZIP_CREATE_ERROR);
        }

        $closed = false;
        try {
            foreach ($entities as $entity) {
                $this->addEntity($zip, $entity, $directory);
            }

            $closed = $zip->close();
            if (! $closed) {
                throw new RuntimeException(self::ZIP_CREATE_ERROR);
            }

            Log::info('Disk entities exported', ['entity_count' => $entities->count()]);

            return $zipPath;
        } catch (Throwable $exception) {
            Log::error('disk.export.failed', [
                'path_hash' => hash('sha256', $directory),
                'exception' => $exception::class,
            ]);
            throw $exception;
        } finally {
            if (! $closed) {
                $zip->close();
                File::delete($zipPath);
            }
        }
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $parts = explode('/', $normalized);

        if (in_array('..', $parts, true) || in_array('.', $parts, true) || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Недопустимый путь экспорта');
        }

        return rtrim('/'.trim($normalized, '/'), '/') ?: '/';
    }

    private function wherePathWithin(Builder $query, string $path): Builder
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $path);
        $prefix = $path === '/' ? $escaped.'%' : $escaped.'/%';

        return $query->where(function (Builder $paths) use ($path, $prefix) {
            $paths->where('path', $path)
                ->orWhereRaw("path LIKE ? ESCAPE '!'", [$prefix]);
        });
    }

    private function addEntity(ZipArchive $zip, DiskEntity $entity, string $directory): void
    {
        $entityPath = "{$entity->path}/{$entity->title}";
        $localPath = Storage::disk('public')->path('entities/available'.$entityPath);
        $relativePath = trim(Str::replaceFirst($directory, '', $entityPath), '/');

        if (is_dir($localPath)) {
            $added = $zip->addEmptyDir($relativePath);
        } elseif (is_file($localPath)) {
            $added = $zip->addFile($localPath, $relativePath);
        } else {
            $added = false;
        }

        if (! $added) {
            throw new RuntimeException(self::ZIP_CREATE_ERROR);
        }
    }
}
