<?php

namespace App\Services;

use App\Enums\DiskEntityTypeEnum;
use App\Jobs\DeleteOldDiskEntityJob;
use App\Models\DiskEntity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                $query->where('path', 'like', "$diskEntityPath%")
                    ->orWhere('original_path', 'like', "$diskEntityPath%")
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
                            preg_replace("#^$diskEntityPath?#", "/$diskEntity->title", $path),
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

            Storage::move($dirPath, $parentDir);
        });

        if ($duplicateInTrash && $entity->type->value !== DiskEntityTypeEnum::FILE->value) {
            $entity->delete();
        }

        if (Storage::exists($sourcePath)) {
            if (! is_dir(Storage::path($destinationPath))) {
                Storage::move($sourcePath, $destinationPath);

                return;
            }

            if (! Storage::exists($destinationPath)) {
                Storage::makeDirectory($destinationPath);
            }

            $this->moveEntities($sourcePath, $destinationPath);
        }
    }

    private function moveEntities(string $sourcePath, string $destinationPath): void
    {
        $allFilesAndDirs = Storage::allFiles($sourcePath);
        $allDirectories = Storage::allDirectories($sourcePath);
        foreach ($allFilesAndDirs as $file) {
            $newPath = str_replace(ltrim($sourcePath, '/'), $destinationPath, $file);
            Storage::move($file, $newPath);
        }

        foreach ($allDirectories as $directory) {
            $newPath = str_replace(ltrim($sourcePath, '/'), $destinationPath, $directory);
            if (! Storage::exists($newPath)) {
                Storage::makeDirectory($newPath);
            }
            Storage::deleteDirectory($directory);
        }

        Storage::deleteDirectory($sourcePath);
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
                if (Storage::exists($path)) {
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

            if (Storage::exists($dirPath)) {
                Storage::move($dirPath, $parentDir);
            }
        });

        if (Storage::exists($sourcePath)) {
            if (! is_dir(Storage::path($sourcePath))) {
                Storage::move($sourcePath, $destinationPath);

                return;
            }

            if (! Storage::exists($destinationPath)) {
                Storage::makeDirectory($destinationPath);
            }

            $this->moveEntities($sourcePath, $destinationPath);
        }
    }

    public function restore(DiskEntity $entity): void
    {
        $this->restoreFromTrash($entity);

        DiskEntity::query()
            ->where('original_path', 'like', "$entity->original_path/$entity->title%")
            ->orWhere('id', $entity->id)
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
        $trimmedPath = rtrim($path, '/');

        $diskEntities = DiskEntity::where('path', 'like', "$trimmedPath%")->where('deleted', false)->get();

        if ($diskEntities->isEmpty()) {
            throw new \Exception(self::ERROR_MESSAGES['noFilesError']);
        }

        $zipFileName = $trimmedPath === '' ? 'root.zip' : Str::afterLast($trimmedPath, '/').'.zip';

        $zip = new ZipArchive;
        $zipFilePath = public_path("storage/$zipFileName");

        if ($zip->open($zipFilePath, ZipArchive::CREATE) !== true) {
            throw new \Exception(self::ERROR_MESSAGES['zipCreateError']);
        }

        foreach ($diskEntities as $entity) {
            $localPath = storage_path('app/public/entities/available'."{$entity->path}/{$entity->title}");
            $relativePath = trim(Str::replaceFirst($trimmedPath, '', "{$entity->path}/{$entity->title}"), '/');

            if (is_dir($localPath)) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($localPath, $relativePath);
            }
        }

        $zip->close();

        return $zipFilePath;
    }

    private function deleteByPath(DiskEntity $entity): void
    {
        if ($entity->path !== '/') {
            $dirs = $this->getEntityHierarchy($entity, true);
            foreach ($dirs as $dir) {
                if (Storage::exists('/entities/trash'.'/'.$dir->id.'_'.$dir->title)) {
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

        if (Storage::exists($fullPath)) {
            is_dir(Storage::path($fullPath)) ?
                Storage::deleteDirectory($fullPath) :
                Storage::delete($fullPath);
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

        $query = DiskEntity::query();

        foreach ($conditions as $condition) {
            $query->orWhere(function ($subQuery) use ($condition) {
                $subQuery->where('original_path', $condition['path'])
                    ->where('title', $condition['title']);
            })->where('deleted', $deleted);
        }

        return $query->get();
    }
}
