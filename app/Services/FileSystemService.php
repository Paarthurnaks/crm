<?php

namespace App\Services;

use App\Enums\DiskEntityTypeEnum;
use App\Jobs\DeleteOldDiskEntityJob;
use App\Models\DiskEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class FileSystemService
{
    private const ERROR_MESSAGES = [
        'zipCreateError' => 'Произошла ошибка при создании файла',
        'noFilesError' => 'Папка пустая',
    ];

    public function deleteDiskEntity(DiskEntity $diskEntity): void
    {
        $diskEntityPath = rtrim($diskEntity->path, '/')."/$diskEntity->title";

        DiskEntity::query()
            ->where(function ($query) use ($diskEntityPath, $diskEntity) {
                $query->where(fn (Builder $pathQuery) => $this->wherePathWithin($pathQuery, 'path', $diskEntityPath))
                    ->orWhere(fn (Builder $pathQuery) => $this->wherePathWithin($pathQuery, 'original_path', $diskEntityPath))
                    ->orWhere('id', $diskEntity->id);
            })
            ->get()
            ->each(function ($entity) use ($diskEntity, $diskEntityPath) {
                if ($diskEntity->deleted) {
                    $entity->delete();
                } else {
                    $path = is_null($entity->original_path) ? $entity->path : $entity->original_path;

                    $entity->update([
                        'deleted' => true,
                        'original_path' => $path,
                        'path' => $entity->id === $diskEntity->id ?
                            '/' :
                            preg_replace('#^'.preg_quote($diskEntityPath, '#').'(?=/|$)#', '/'.$diskEntity->title, $path),
                    ]);
                }
            });

        if ($diskEntity->deleted) {
            $this->deleteByPath($diskEntity);

            return;
        }

        $this->moveToTrash($diskEntity->refresh());
        $this->startDeletionJob($diskEntity);
    }

    private function startDeletionJob(DiskEntity $diskEntity): void
    {
        $deletionDays = config('constants.disk_entity.deletion_days');
        DeleteOldDiskEntityJob::dispatch($diskEntity, app(FileSystemService::class))->delay(now()->addDays($deletionDays));
    }

    private function moveToTrash(DiskEntity $entity): void
    {
        $entityPath = rtrim($entity->original_path, '/')."/$entity->title";

        $duplicateInTrash = DiskEntity::query()
            ->where('original_path', $entity->original_path)
            ->where('title', $entity->title)
            ->where('id', '!=', $entity->id)
            ->where('deleted', true)
            ->first();

        $sourcePath = 'entities/available'.$entityPath;

        $destinationPath = 'entities/trash'.($duplicateInTrash ?
            rtrim($duplicateInTrash->path, '/')."/$duplicateInTrash->id".'_'.$duplicateInTrash->title :
            rtrim($entity->path, '/')."/$entity->id".'_'.$entity->title);

        if ($duplicateInTrash && $entity->type->value === DiskEntityTypeEnum::FILE->value) {
            $fileDestinationPath = 'entities/trash'.rtrim($entity->path, '/')."/$entity->id".'_'.$entity->title;
            // для уникальности и предотвращения коллизий в файловой системе
            $destinationPath = Str::beforeLast($fileDestinationPath, '.').'_'.$entity->id.'.'.Str::afterLast($fileDestinationPath, '.');

            $entity->update([
                'title' => Str::before($entity->title, '.').'_'.$entity->id.'.'.Str::afterLast($fileDestinationPath, '.'),
            ]);
        }

        $entityFirstLevelSubDirs = DiskEntity::query()
            ->where('original_path', $entityPath)
            ->where('deleted', true)
            ->get();

        $entityFirstLevelSubDirs->each(function ($dir) use ($destinationPath) {
            $dirPath = 'entities/trash'.'/'.$dir->id.'_'.$dir->title;
            $parentDir = $destinationPath."/$dir->title";

            $this->moveOrFail($dirPath, $parentDir);
        });

        if ($duplicateInTrash && $entity->type->value !== DiskEntityTypeEnum::FILE->value) {
            $entity->delete();
        }

        if (Storage::disk('public')->exists($sourcePath)) {
            if (! is_dir(Storage::disk('public')->path($destinationPath))) {
                $this->moveOrFail($sourcePath, $destinationPath);

                return;
            }

            if (! Storage::disk('public')->exists($destinationPath)) {
                Storage::disk('public')->makeDirectory($destinationPath);
            }

            $this->moveEntities($sourcePath, $destinationPath);
        }
    }

    private function moveEntities(string $sourcePath, string $destinationPath): void
    {
        $allFilesAndDirs = Storage::disk('public')->allFiles($sourcePath);
        $allDirectories = Storage::disk('public')->allDirectories($sourcePath);
        foreach ($allFilesAndDirs as $file) {
            $newPath = str_replace(ltrim($sourcePath, '/'), $destinationPath, $file);
            $this->moveOrFail($file, $newPath);
        }

        foreach ($allDirectories as $directory) {
            $newPath = str_replace(ltrim($sourcePath, '/'), $destinationPath, $directory);
            if (! Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->makeDirectory($newPath);
            }
            Storage::disk('public')->deleteDirectory($directory);
        }

        Storage::disk('public')->deleteDirectory($sourcePath);
    }

    private function restoreFromTrash(DiskEntity $entity): void
    {
        $entityPath = rtrim($entity->original_path, '/')."/$entity->title";

        $duplicateInDisk = DiskEntity::query()
            ->where('path', $entity->original_path)
            ->where('title', $entity->title)
            ->where('id', '!=', $entity->id)
            ->where('deleted', false)
            ->first();

        $sourcePath = 'entities/trash'.'/'.$entity->id.'_'.$entity->title;

        $destinationPath = 'entities/available'.($duplicateInDisk ?
             rtrim($duplicateInDisk->path, '/').'/'.$duplicateInDisk->title :
             $entityPath);

        if ($duplicateInDisk && $entity->type->value === DiskEntityTypeEnum::FILE->value) {
            $filePath = 'entities/available'.rtrim($entity->original_path, '/')."/$entity->id".'_'.$entity->title;
            $fileDestinationPath = Str::beforeLast($filePath, '/').'/'.ltrim(Str::afterLast($filePath, '/'), $entity->id.'_');
            // для уникальности и предотвращения коллизий в файловой системе
            $destinationPath = Str::beforeLast(ltrim($fileDestinationPath, $entity->id.'_'), '.').'_'.$entity->id.'.'.Str::afterLast($fileDestinationPath, '.');

            $entity->update([
                'title' => Str::before($entity->title, '.').'_'.$entity->id.'.'.Str::afterLast($fileDestinationPath, '.'),
            ]);
        }
        if ($entity->path !== '/') {
            $dirs = $this->getEntityHierarchy($entity, true);

            foreach ($dirs as $dir) {
                $path = '/entities/trash'.'/'.$dir->id.'_'.$dir->title;
                if (Storage::disk('public')->exists($path)) {
                    $availableDirIsExists = DiskEntity::query()
                        ->where('path', $dir->original_path)
                        ->where('deleted', false)
                        ->exists();

                    // Создание новой иерархии родителей в бд при восстановлении
                    if (! $availableDirIsExists) {
                        DiskEntity::create([
                            'path' => $dir->original_path,
                            'title' => $dir->title,
                            'size' => $dir->size,
                            'who_created_id' => $dir->who_created_id,
                            'type' => DiskEntityTypeEnum::FOLDER->value,
                        ]);
                    }

                    $sourcePath = $path.Str::afterFirst($entity->path, '/'.$dir->title).'/'.$entity->title;
                }
            }
        }

        if ($duplicateInDisk && $entity->type->value !== DiskEntityTypeEnum::FILE->value) {
            $entity->delete();
        }

        $entityFirstLevelSubDirs = DiskEntity::query()
            ->where('original_path', $entityPath)
            ->where('deleted', true)
            ->get();

        $entityFirstLevelSubDirs->each(function ($dir) use ($destinationPath) {
            $dirPath = 'entities/available'.'/'.$dir->id.'_'.$dir->title;
            $parentDir = $destinationPath."/$dir->title";

            if (Storage::disk('public')->exists($dirPath)) {
                $this->moveOrFail($dirPath, $parentDir);
            }
        });

        if (Storage::disk('public')->exists($sourcePath)) {
            if (! is_dir(Storage::disk('public')->path($sourcePath))) {
                $this->moveOrFail($sourcePath, $destinationPath);

                return;
            }

            if (! Storage::disk('public')->exists($destinationPath)) {
                Storage::disk('public')->makeDirectory($destinationPath);
            }

            $this->moveEntities($sourcePath, $destinationPath);
        }
    }

    public function restore(DiskEntity $entity): void
    {
        $this->restoreFromTrash($entity);

        DiskEntity::query()
            ->where(function (Builder $query) use ($entity) {
                $entityPath = rtrim($entity->original_path, '/').'/'.$entity->title;
                $query->where(fn (Builder $pathQuery) => $this->wherePathWithin($pathQuery, 'original_path', $entityPath))
                    ->orWhere('id', $entity->id);
            })
            ->where('deleted', true)
            ->get()
            ->each(function ($entity) {
                $entity->update([
                    'deleted' => false,
                    'path' => $entity->original_path,
                    'original_path' => null,
                ]);
            });
    }

    public function createExportedZip(string $path): string
    {
        $parts = explode('/', str_replace('\\', '/', $path));
        if (in_array('..', $parts, true) || in_array('.', $parts, true) || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Недопустимый путь экспорта');
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $trimmedPath = rtrim('/'.trim($normalizedPath, '/'), '/');

        $diskEntities = DiskEntity::query()
            ->where(fn (Builder $query) => $this->wherePathWithin($query, 'path', $trimmedPath ?: '/'))
            ->where('deleted', false)
            ->get();

        if ($diskEntities->isEmpty()) {
            throw new \Exception(self::ERROR_MESSAGES['noFilesError']);
        }

        $zip = new ZipArchive;
        $zipDirectory = storage_path('app/tmp/exports');
        File::ensureDirectoryExists($zipDirectory);
        $zipFilePath = $zipDirectory.DIRECTORY_SEPARATOR.Str::random(24).'.zip';

        if ($zip->open($zipFilePath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException(self::ERROR_MESSAGES['zipCreateError']);
        }

        $closed = false;
        try {
            foreach ($diskEntities as $entity) {
                $localPath = Storage::disk('public')->path('entities/available'."{$entity->path}/{$entity->title}");
                $relativePath = trim(Str::replaceFirst($trimmedPath, '', "{$entity->path}/{$entity->title}"), '/');

                if (is_dir($localPath)) {
                    $added = $zip->addEmptyDir($relativePath);
                } elseif (is_file($localPath)) {
                    $added = $zip->addFile($localPath, $relativePath);
                } else {
                    $added = false;
                }

                if (! $added) {
                    throw new RuntimeException(self::ERROR_MESSAGES['zipCreateError']);
                }
            }

            $closed = $zip->close();
            if (! $closed) {
                throw new RuntimeException(self::ERROR_MESSAGES['zipCreateError']);
            }

            Log::info('Disk entities exported', ['entity_count' => $diskEntities->count()]);

            return $zipFilePath;
        } finally {
            if (! $closed) {
                $zip->close();
                File::delete($zipFilePath);
            }
        }
    }

    private function deleteByPath(DiskEntity $entity): void
    {
        if ($entity->path !== '/') {
            $dirs = $this->getEntityHierarchy($entity, true);
            foreach ($dirs as $dir) {
                if (Storage::disk('public')->exists('/entities/trash'.'/'.$dir->id.'_'.$dir->title)) {
                    $path = '/'.$dir->id.'_'.$dir->title.'/'.$entity->title;
                    $this->deleteEntityFromStorage($path);

                    return;
                }
            }
        }

        $path = rtrim($entity->path).$entity->id.'_'.$entity->title;

        $this->deleteEntityFromStorage($path);
    }

    private function deleteEntityFromStorage(string $path): void
    {
        $fullPath = '/entities/trash'.$path;

        if (Storage::disk('public')->exists($fullPath)) {
            is_dir(Storage::disk('public')->path($fullPath)) ?
                Storage::disk('public')->deleteDirectory($fullPath) :
                Storage::disk('public')->delete($fullPath);
        }
    }

    private function getEntityHierarchy(DiskEntity $entity, bool $deleted): Collection
    {
        $trimmedPath = trim($entity->original_path, '/');
        $parts = explode('/', $trimmedPath);
        $conditions = [];

        $conditions[] = ['title' => $entity->title, 'path' => $entity->original_path];

        for ($i = count($parts) - 1; $i >= 0; $i--) {
            $subPath = '/'.implode('/', array_slice($parts, 0, $i));
            $title = $parts[$i];
            $conditions[] = ['path' => $subPath ?: '/', 'title' => $title];
        }

        return DiskEntity::query()
            ->where('deleted', $deleted)
            ->where(function (Builder $query) use ($conditions) {
                foreach ($conditions as $condition) {
                    $query->orWhere(function (Builder $subQuery) use ($condition) {
                        $subQuery->where('original_path', $condition['path'])
                            ->where('title', $condition['title']);
                    });
                }
            })
            ->get();
    }

    private function wherePathWithin(Builder $query, string $column, string $path): Builder
    {
        $path = rtrim($path, '/') ?: '/';
        $escapedPath = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $path);
        $prefix = $path === '/' ? $escapedPath.'%' : $escapedPath.'/%';

        return $query->where(function (Builder $pathQuery) use ($column, $path, $prefix) {
            $pathQuery->where($column, $path)
                ->orWhereRaw("$column LIKE ? ESCAPE '!'", [$prefix]);
        });
    }

    private function moveOrFail(string $source, string $destination): void
    {
        if (Storage::disk('public')->move($source, $destination)) {
            Log::debug('Disk entity moved', ['source_hash' => hash('sha256', $source), 'destination_hash' => hash('sha256', $destination)]);

            return;
        }

        Log::error('Disk entity move failed', ['source_hash' => hash('sha256', $source), 'destination_hash' => hash('sha256', $destination)]);

        throw new RuntimeException('Не удалось переместить файл или папку');
    }
}
