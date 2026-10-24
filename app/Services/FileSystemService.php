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
use Throwable;
use ZipArchive;

class FileSystemService
{
    private const ERROR_MESSAGES = [
        'zipCreateError' => 'Произошла ошибка при создании файла',
        'noFilesError' => 'Папка пустая',
    ];

    /**
     * Доступный элемент сначала перемещается в корзину, затем обновляются записи.
     * Для элемента в корзине сначала удаляется содержимое Storage, затем записи БД.
     */
    public function deleteDiskEntity(DiskEntity $diskEntity): void
    {
        $diskEntityPath = $this->relativeEntityPath($diskEntity);

        if ($diskEntity->deleted) {
            $this->deleteByPath($diskEntity);
        } else {
            $this->moveToTrash($diskEntity);
        }

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

        if (! $diskEntity->deleted && $diskEntity->exists) {
            $this->startDeletionJob($diskEntity);
        }

        Log::info('Disk entity state changed', [
            'disk_entity_id' => $diskEntity->id,
            'state' => $diskEntity->deleted ? 'removed' : 'trashed',
        ]);
    }

    private function startDeletionJob(DiskEntity $diskEntity): void
    {
        $deletionDays = config('constants.disk_entity.deletion_days');
        DeleteOldDiskEntityJob::dispatch($diskEntity->id)->delay(now()->addDays($deletionDays))->afterCommit();
    }

    private function moveToTrash(DiskEntity $entity): void
    {
        $entityPath = $this->relativeEntityPath($entity);

        $duplicateInTrash = DiskEntity::query()
            ->where('original_path', $entity->path)
            ->where('title', $entity->title)
            ->where('id', '!=', $entity->id)
            ->where('deleted', true)
            ->first();

        $sourcePath = 'entities/available'.$entityPath;

        $destinationPath = 'entities/trash'.($duplicateInTrash ?
            rtrim($duplicateInTrash->path, '/')."/$duplicateInTrash->id".'_'.$duplicateInTrash->title :
            rtrim($entity->path, '/')."/$entity->id".'_'.$entity->title);
        $renamedTitle = null;

        if ($duplicateInTrash && $entity->type->value === DiskEntityTypeEnum::FILE->value) {
            $fileDestinationPath = 'entities/trash'.rtrim($entity->path, '/')."/$entity->id".'_'.$entity->title;
            // для уникальности и предотвращения коллизий в файловой системе
            $destinationPath = Str::beforeLast($fileDestinationPath, '.').'_'.$entity->id.'.'.Str::afterLast($fileDestinationPath, '.');

            $renamedTitle = Str::before($entity->title, '.').'_'.$entity->id.'.'.Str::afterLast($fileDestinationPath, '.');
        }

        if (! Storage::disk('public')->exists($sourcePath)) {
            Log::error('Disk entity source is missing', ['disk_entity_id' => $entity->id]);

            throw new RuntimeException('Файл или папка не найдены на диске');
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

        if (! is_dir(Storage::disk('public')->path($destinationPath))) {
            $this->moveOrFail($sourcePath, $destinationPath);

            if ($renamedTitle !== null) {
                $entity->update(['title' => $renamedTitle]);
            }

            if ($duplicateInTrash && $entity->type->value !== DiskEntityTypeEnum::FILE->value) {
                $entity->delete();
            }

            return;
        }

        if (! Storage::disk('public')->exists($destinationPath)) {
            $this->makeDirectoryOrFail($destinationPath);
        }

        $this->moveEntities($sourcePath, $destinationPath);

        if ($renamedTitle !== null) {
            $entity->update(['title' => $renamedTitle]);
        }

        if ($duplicateInTrash && $entity->type->value !== DiskEntityTypeEnum::FILE->value) {
            $entity->delete();
        }
    }

    private function moveEntities(string $sourcePath, string $destinationPath): void
    {
        $disk = Storage::disk('public');
        $allFilesAndDirs = $disk->allFiles($sourcePath);
        $allDirectories = $disk->allDirectories($sourcePath);
        $moves = [];
        foreach ($allFilesAndDirs as $file) {
            $newPath = Str::replaceFirst(rtrim($sourcePath, '/').'/', rtrim($destinationPath, '/').'/', $file);
            if ($disk->exists($newPath)) {
                Log::warning('Disk entity destination already exists', ['path_hash' => hash('sha256', $newPath)]);

                throw new RuntimeException('Файл с таким именем уже существует');
            }

            $moves[] = [$file, $newPath];
        }

        $completedMoves = [];
        try {
            foreach ($moves as [$source, $destination]) {
                $this->moveOrFail($source, $destination);
                $completedMoves[] = [$source, $destination];
            }

            foreach ($allDirectories as $directory) {
                $newPath = Str::replaceFirst(rtrim($sourcePath, '/').'/', rtrim($destinationPath, '/').'/', $directory);
                if (! $disk->exists($newPath)) {
                    $this->makeDirectoryOrFail($newPath);
                }
                $this->deleteDirectoryOrFail($directory);
            }

            $this->deleteDirectoryOrFail($sourcePath);
        } catch (Throwable $exception) {
            foreach (array_reverse($completedMoves) as [$source, $destination]) {
                if (! $disk->move($destination, $source)) {
                    Log::error('Disk entity move rollback failed', [
                        'source_hash' => hash('sha256', $source),
                        'destination_hash' => hash('sha256', $destination),
                    ]);
                }
            }

            throw $exception;
        }
    }

    private function restoreFromTrash(DiskEntity $entity): void
    {
        $missingParents = [];
        $entityPath = rtrim($entity->original_path, '/')."/$entity->title";

        $duplicateInDisk = DiskEntity::query()
            ->where('path', $entity->original_path)
            ->where('title', $entity->title)
            ->where('id', '!=', $entity->id)
            ->where('deleted', false)
            ->first();

        $sourcePath = $this->locateTrashedEntityPath($entity);

        $destinationPath = 'entities/available'.($duplicateInDisk ?
             rtrim($duplicateInDisk->path, '/').'/'.$duplicateInDisk->title :
             $entityPath);
        $renamedTitle = null;

        if ($duplicateInDisk && $entity->type->value === DiskEntityTypeEnum::FILE->value) {
            $filePath = 'entities/available'.rtrim($entity->original_path, '/')."/$entity->id".'_'.$entity->title;
            $fileDestinationPath = Str::beforeLast($filePath, '/').'/'.ltrim(Str::afterLast($filePath, '/'), $entity->id.'_');
            // для уникальности и предотвращения коллизий в файловой системе
            $destinationPath = Str::beforeLast(ltrim($fileDestinationPath, $entity->id.'_'), '.').'_'.$entity->id.'.'.Str::afterLast($fileDestinationPath, '.');

            $renamedTitle = Str::before($entity->title, '.').'_'.$entity->id.'.'.Str::afterLast($fileDestinationPath, '.');
        }
        if ($entity->path !== '/') {
            $dirs = $this->getEntityHierarchy($entity, true);

            foreach ($dirs as $dir) {
                $path = $this->trashEntityPath($dir);
                if (Storage::disk('public')->exists($path)) {
                    $availableDirIsExists = DiskEntity::query()
                        ->where('path', $dir->original_path)
                        ->where('deleted', false)
                        ->exists();

                    // Создание новой иерархии родителей в бд при восстановлении
                    if (! $availableDirIsExists) {
                        $missingParents[] = [
                            'path' => $dir->original_path,
                            'title' => $dir->title,
                            'size' => $dir->size,
                            'who_created_id' => $dir->who_created_id,
                            'type' => DiskEntityTypeEnum::FOLDER->value,
                        ];
                    }

                }
            }
        }

        if (! Storage::disk('public')->exists($sourcePath)) {
            Log::error('Trashed disk entity source is missing', ['disk_entity_id' => $entity->id]);

            throw new RuntimeException('Файл или папка не найдены в корзине');
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

        if (! is_dir(Storage::disk('public')->path($sourcePath))) {
            $this->moveOrFail($sourcePath, $destinationPath);
        } else {
            if (! Storage::disk('public')->exists($destinationPath)) {
                $this->makeDirectoryOrFail($destinationPath);
            }

            $this->moveEntities($sourcePath, $destinationPath);
        }

        foreach ($missingParents as $parent) {
            DiskEntity::firstOrCreate(
                ['path' => $parent['path'], 'title' => $parent['title'], 'deleted' => false],
                $parent
            );
        }

        if ($renamedTitle !== null) {
            $entity->update(['title' => $renamedTitle]);
        }

        if ($duplicateInDisk && $entity->type->value !== DiskEntityTypeEnum::FILE->value) {
            $entity->delete();
        }
    }

    /**
     * Восстановление перемещает содержимое в доступный каталог до смены состояния записей.
     */
    public function restore(DiskEntity $entity): void
    {
        if (! $entity->deleted) {
            Log::warning('Disk entity restore skipped', ['disk_entity_id' => $entity->id, 'reason' => 'available']);

            return;
        }

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

        Log::info('Disk entity state changed', ['disk_entity_id' => $entity->id, 'state' => 'available']);
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
        $this->deleteEntityFromStorage($this->locateTrashedEntityPath($entity));
    }

    private function deleteEntityFromStorage(string $fullPath): void
    {
        if (Storage::disk('public')->exists($fullPath)) {
            $deleted = is_dir(Storage::disk('public')->path($fullPath)) ?
                Storage::disk('public')->deleteDirectory($fullPath) :
                Storage::disk('public')->delete($fullPath);

            if (! $deleted) {
                Log::error('Disk entity storage deletion failed', ['path_hash' => hash('sha256', $fullPath)]);

                throw new RuntimeException('Не удалось удалить файл или папку');
            }
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

    private function relativeEntityPath(DiskEntity $entity): string
    {
        return rtrim($entity->path, '/').'/'.$entity->title;
    }

    private function trashEntityPath(DiskEntity $entity): string
    {
        return 'entities/trash'.rtrim($entity->original_path, '/').'/'.$entity->id.'_'.$entity->title;
    }

    private function locateTrashedEntityPath(DiskEntity $entity): string
    {
        $directPath = $this->trashEntityPath($entity);
        if (Storage::disk('public')->exists($directPath)) {
            return $directPath;
        }

        $relativePath = rtrim($entity->original_path, '/').'/'.$entity->title;
        foreach ($this->getEntityHierarchy($entity, true) as $ancestor) {
            if ($ancestor->id === $entity->id) {
                continue;
            }

            $ancestorPath = rtrim($ancestor->original_path, '/').'/'.$ancestor->title;
            if (! str_starts_with($relativePath, $ancestorPath.'/')) {
                continue;
            }

            $storedAncestorPath = $this->trashEntityPath($ancestor);
            $candidatePath = $storedAncestorPath.substr($relativePath, strlen($ancestorPath));
            if (Storage::disk('public')->exists($candidatePath)) {
                return $candidatePath;
            }
        }

        return $directPath;
    }

    private function makeDirectoryOrFail(string $path): void
    {
        if (! Storage::disk('public')->makeDirectory($path)) {
            Log::error('Disk entity directory creation failed', ['path_hash' => hash('sha256', $path)]);

            throw new RuntimeException('Не удалось создать папку');
        }
    }

    private function deleteDirectoryOrFail(string $path): void
    {
        if (! Storage::disk('public')->deleteDirectory($path)) {
            Log::error('Disk entity directory deletion failed', ['path_hash' => hash('sha256', $path)]);

            throw new RuntimeException('Не удалось удалить папку');
        }
    }
}
