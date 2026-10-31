<?php

namespace App\Services;

use App\Enums\DiskEntityTypeEnum;
use App\Jobs\DeleteOldDiskEntityJob;
use App\Models\DiskEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FileSystemService
{
    private const AVAILABLE_DIRECTORY = 'entities/available';

    private const TRASH_DIRECTORY = 'entities/trash';

    public function createFolder(array $data, User $actor): DiskEntity
    {
        $path = $this->normalizeParentPath($data['path']);
        $folderPath = $this->availablePath($path, $data['title']);
        $disk = Storage::disk('public');
        $created = ! $disk->exists($folderPath);

        if (! $disk->makeDirectory($folderPath)) {
            Log::error('disk.folder.create.failed', ['actor_id' => $actor->id]);
            throw new RuntimeException('Не удалось создать папку');
        }

        try {
            $entity = DB::transaction(function () use ($data, $path, $actor): DiskEntity {
                $entity = DiskEntity::create([
                    'title' => $data['title'],
                    'type' => DiskEntityTypeEnum::FOLDER->value,
                    'who_created_id' => $actor->id,
                    'path' => $path,
                    'size' => 0,
                ]);
                $actor->role->availableDiskEntities()->attach($entity);

                return $entity;
            });
        } catch (Throwable $exception) {
            if ($created) {
                $disk->deleteDirectory($folderPath);
            }
            Log::error('disk.folder.create.failed', ['actor_id' => $actor->id, 'exception' => $exception::class]);
            throw $exception;
        }

        Log::info('disk.folder.created', ['entity_id' => $entity->id]);

        return $entity;
    }

    public function createFile(array $data, User $actor): DiskEntity
    {
        /** @var UploadedFile $file */
        $file = $data['file'];
        $path = $this->normalizeParentPath($data['path']);
        $filename = $file->getClientOriginalName();
        $folderPath = $this->availablePath($path);
        $storagePath = $this->availablePath($path, $filename);
        $disk = Storage::disk('public');

        if ($disk->exists($storagePath)) {
            Log::warning('disk.file.create.conflict', ['actor_id' => $actor->id]);
            throw new RuntimeException('Файл с таким именем уже существует');
        }

        if ($file->storeAs($folderPath, $filename, 'public') === false) {
            Log::error('disk.file.store.failed', ['actor_id' => $actor->id]);
            throw new RuntimeException('Не удалось сохранить файл');
        }

        try {
            $entity = DB::transaction(function () use ($path, $actor, $file, $filename): DiskEntity {
                $entity = DiskEntity::create([
                    'title' => $filename,
                    'type' => DiskEntityTypeEnum::FILE->value,
                    'who_created_id' => $actor->id,
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ]);
                $actor->role->availableDiskEntities()->attach($entity);

                return $entity;
            });
        } catch (Throwable $exception) {
            $disk->delete($storagePath);
            Log::error('disk.file.create.failed', ['actor_id' => $actor->id, 'exception' => $exception::class]);
            throw $exception;
        }

        Log::info('disk.file.created', ['entity_id' => $entity->id]);

        return $entity;
    }

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

        $this->updateEntitiesAfterDeletion($diskEntity, $diskEntityPath);

        if (! $diskEntity->deleted && $diskEntity->exists) {
            $this->startDeletionJob($diskEntity);
        }

        Log::info('Disk entity state changed', [
            'disk_entity_id' => $diskEntity->id,
            'state' => $diskEntity->deleted ? 'removed' : 'trashed',
        ]);
    }

    private function updateEntitiesAfterDeletion(DiskEntity $diskEntity, string $diskEntityPath): void
    {
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
                        'path' => $entity->id === $diskEntity->id
                            ? '/'
                            : $this->pathWithinTrash($diskEntity, $diskEntityPath, $path),
                    ]);
                }
            });
    }

    private function pathWithinTrash(DiskEntity $root, string $rootPath, string $path): string
    {
        if ($path === $rootPath) {
            return '/'.$root->title;
        }

        if (str_starts_with($path, $rootPath.'/')) {
            return '/'.$root->title.substr($path, strlen($rootPath));
        }

        return $path;
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

        $sourcePath = $this->availablePath($entity->path, $entity->title);
        [$destinationPath, $renamedTitle] = $this->trashDestination($entity, $duplicateInTrash);

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

            $this->finishTrashMove($entity, $renamedTitle, $duplicateInTrash !== null);

            return;
        }

        if (! Storage::disk('public')->exists($destinationPath)) {
            $this->makeDirectoryOrFail($destinationPath);
        }

        $this->moveEntities($sourcePath, $destinationPath);
        $this->finishTrashMove($entity, $renamedTitle, $duplicateInTrash !== null);
    }

    private function finishTrashMove(DiskEntity $entity, ?string $renamedTitle, bool $duplicateInTrash): void
    {
        if ($renamedTitle !== null) {
            $entity->update(['title' => $renamedTitle]);
        }

        if ($duplicateInTrash && $entity->type->value !== DiskEntityTypeEnum::FILE->value) {
            $entity->delete();
        }
    }

    /** @return array{string, ?string} */
    private function trashDestination(DiskEntity $entity, ?DiskEntity $duplicateInTrash): array
    {
        if ($duplicateInTrash && $entity->type->value === DiskEntityTypeEnum::FILE->value) {
            $renamedTitle = $this->titleWithEntityId($entity);
            Log::debug('disk.file.trash_name_resolved', ['entity_id' => $entity->id]);

            return [
                $this->storagePath(self::TRASH_DIRECTORY, $entity->path, $entity->id.'_'.$renamedTitle),
                $renamedTitle,
            ];
        }

        return [
            $this->storagePath(self::TRASH_DIRECTORY, $duplicateInTrash?->path ?? $entity->path, $entity->id.'_'.$entity->title),
            null,
        ];
    }

    private function moveEntities(string $sourcePath, string $destinationPath): void
    {
        $disk = Storage::disk('public');
        $allDirectories = $disk->allDirectories($sourcePath);
        $moves = $this->plannedFileMoves($disk->allFiles($sourcePath), $sourcePath, $destinationPath);

        $completedMoves = [];
        try {
            foreach ($moves as [$source, $destination]) {
                $this->moveOrFail($source, $destination);
                $completedMoves[] = [$source, $destination];
            }

            $this->moveDirectories($allDirectories, $sourcePath, $destinationPath);

            $this->deleteDirectoryOrFail($sourcePath);
        } catch (Throwable $exception) {
            $this->rollbackFileMoves($completedMoves);

            throw $exception;
        }
    }

    private function plannedFileMoves(array $files, string $sourcePath, string $destinationPath): array
    {
        $moves = [];
        foreach ($files as $file) {
            $newPath = Str::replaceFirst(rtrim($sourcePath, '/').'/', rtrim($destinationPath, '/').'/', $file);
            if (Storage::disk('public')->exists($newPath)) {
                Log::warning('Disk entity destination already exists', ['path_hash' => hash('sha256', $newPath)]);

                throw new RuntimeException('Файл с таким именем уже существует');
            }

            $moves[] = [$file, $newPath];
        }

        return $moves;
    }

    private function moveDirectories(array $directories, string $sourcePath, string $destinationPath): void
    {
        foreach ($directories as $directory) {
            $newPath = Str::replaceFirst(rtrim($sourcePath, '/').'/', rtrim($destinationPath, '/').'/', $directory);
            if (! Storage::disk('public')->exists($newPath)) {
                $this->makeDirectoryOrFail($newPath);
            }
            $this->deleteDirectoryOrFail($directory);
        }
    }

    private function rollbackFileMoves(array $completedMoves): void
    {
        foreach (array_reverse($completedMoves) as [$source, $destination]) {
            if (! Storage::disk('public')->move($destination, $source)) {
                Log::error('Disk entity move rollback failed', [
                    'source_hash' => hash('sha256', $source),
                    'destination_hash' => hash('sha256', $destination),
                ]);
            }
        }
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

        $sourcePath = $this->locateTrashedEntityPath($entity);

        [$destinationPath, $renamedTitle] = $this->restoreDestination($entity, $duplicateInDisk);
        $missingParents = $this->missingRestoreParents($entity);

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

    /** @return array{string, ?string} */
    private function restoreDestination(DiskEntity $entity, ?DiskEntity $duplicateInDisk): array
    {
        if ($duplicateInDisk && $entity->type->value === DiskEntityTypeEnum::FILE->value) {
            $renamedTitle = $this->titleWithEntityId($entity);
            Log::debug('disk.file.restore_name_resolved', ['entity_id' => $entity->id]);

            return [$this->availablePath($entity->original_path, $renamedTitle), $renamedTitle];
        }

        return [
            $this->availablePath($duplicateInDisk?->path ?? $entity->original_path, $duplicateInDisk?->title ?? $entity->title),
            null,
        ];
    }

    private function missingRestoreParents(DiskEntity $entity): array
    {
        if ($entity->path === '/') {
            return [];
        }

        $parents = [];
        foreach ($this->getEntityHierarchy($entity, true) as $dir) {
            if (! Storage::disk('public')->exists($this->trashEntityPath($dir))) {
                continue;
            }

            $available = DiskEntity::query()
                ->where('path', $dir->original_path)
                ->where('deleted', false)
                ->exists();

            if (! $available) {
                $parents[] = [
                    'path' => $dir->original_path,
                    'title' => $dir->title,
                    'size' => $dir->size,
                    'who_created_id' => $dir->who_created_id,
                    'type' => DiskEntityTypeEnum::FOLDER->value,
                ];
            }
        }

        return $parents;
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

        $this->updateRestoredEntities($entity);

        Log::info('Disk entity state changed', ['disk_entity_id' => $entity->id, 'state' => 'available']);
    }

    private function updateRestoredEntities(DiskEntity $entity): void
    {
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
        return (new FileSystemZipExporter)->export($path);
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

    private function normalizeParentPath(string $path): string
    {
        return rtrim($path, '/') ?: '/';
    }

    private function availablePath(string $parentPath, ?string $title = null): string
    {
        return $this->storagePath(self::AVAILABLE_DIRECTORY, $parentPath, $title);
    }

    private function storagePath(string $directory, string $parentPath, ?string $title = null): string
    {
        $path = $directory.'/'.trim($parentPath, '/');

        return rtrim($path, '/').($title === null ? '' : '/'.$title);
    }

    private function titleWithEntityId(DiskEntity $entity): string
    {
        $extension = pathinfo($entity->title, PATHINFO_EXTENSION);
        $name = pathinfo($entity->title, PATHINFO_FILENAME).'_'.$entity->id;

        return $extension === '' ? $name : $name.'.'.$extension;
    }

    private function trashEntityPath(DiskEntity $entity): string
    {
        return $this->storagePath(
            self::TRASH_DIRECTORY,
            $entity->original_path,
            $entity->id.'_'.$entity->title
        );
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
