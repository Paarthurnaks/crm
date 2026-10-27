<?php

namespace Tests\Feature;

use App\Enums\DiskEntityTypeEnum;
use App\Models\DiskEntity;
use App\Services\FileSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DiskEntityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_folder_moves_to_trash_and_restores_without_touching_sibling(): void
    {
        Queue::fake();
        Storage::fake('public');
        $folder = $this->createEntity('/a', 'folder', DiskEntityTypeEnum::FOLDER);
        $child = $this->createEntity('/a/folder', 'doc.txt', DiskEntityTypeEnum::FILE);
        $subfolder = $this->createEntity('/a/folder', 'sub', DiskEntityTypeEnum::FOLDER);
        $nested = $this->createEntity('/a/folder/sub', 'nested.txt', DiskEntityTypeEnum::FILE);
        $sibling = $this->createEntity('/a/folderish', 'other.txt', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/folder/doc.txt', 'document');
        Storage::disk('public')->put('entities/available/a/folder/sub/nested.txt', 'nested');
        Storage::disk('public')->put('entities/available/a/folderish/other.txt', 'other');

        $service = new FileSystemService;
        $service->deleteDiskEntity($folder);

        $this->assertTrue((bool) $folder->fresh()->deleted);
        $this->assertSame('/a/folder', $child->fresh()->original_path);
        $this->assertSame('/a/folder/sub', $nested->fresh()->original_path);
        $this->assertFalse((bool) $sibling->fresh()->deleted);
        Storage::disk('public')->assertExists("entities/trash/a/{$folder->id}_folder/doc.txt");
        Storage::disk('public')->assertExists("entities/trash/a/{$folder->id}_folder/sub/nested.txt");

        $service->restore($folder->fresh());

        $this->assertFalse((bool) $folder->fresh()->deleted);
        $this->assertFalse((bool) $child->fresh()->deleted);
        $this->assertFalse((bool) $subfolder->fresh()->deleted);
        Storage::disk('public')->assertExists('entities/available/a/folder/doc.txt');
        Storage::disk('public')->assertExists('entities/available/a/folder/sub/nested.txt');
        Storage::disk('public')->assertExists('entities/available/a/folderish/other.txt');

        $service->restore($folder->fresh());
        Storage::disk('public')->assertExists('entities/available/a/folder/doc.txt');
    }

    public function test_hard_deletion_removes_storage_before_database_record(): void
    {
        Queue::fake();
        Storage::fake('public');
        $entity = $this->createEntity('/a', 'old.txt', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/old.txt', 'content');

        $service = new FileSystemService;
        $service->deleteDiskEntity($entity);
        $trashed = $entity->fresh();
        Storage::disk('public')->assertExists("entities/trash/a/{$entity->id}_old.txt");

        $service->deleteDiskEntity($trashed);

        $this->assertDatabaseMissing('disk_entities', ['id' => $entity->id]);
        Storage::disk('public')->assertMissing("entities/trash/a/{$entity->id}_old.txt");
    }

    public function test_failed_storage_move_keeps_database_state(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $entity = $this->createEntity('/a', 'doc.txt', DiskEntityTypeEnum::FILE);
        $disk->put('entities/available/a/doc.txt', 'content');

        $failedDisk = Mockery::mock($disk)->makePartial();
        $failedDisk->shouldReceive('move')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($failedDisk);

        try {
            (new FileSystemService)->deleteDiskEntity($entity);
            $this->fail('Ожидалась ошибка перемещения');
        } catch (RuntimeException) {
            $this->assertFalse((bool) $entity->fresh()->deleted);
            $this->assertNull($entity->fresh()->original_path);
            $this->assertTrue($disk->exists('entities/available/a/doc.txt'));
        }
    }

    public function test_failed_storage_delete_keeps_trashed_record(): void
    {
        Queue::fake();
        Storage::fake('public');
        $disk = Storage::disk('public');
        $entity = $this->createEntity('/a', 'doc.txt', DiskEntityTypeEnum::FILE);
        $disk->put('entities/available/a/doc.txt', 'content');
        $service = new FileSystemService;
        $service->deleteDiskEntity($entity);

        $failedDisk = Mockery::mock($disk)->makePartial();
        $failedDisk->shouldReceive('delete')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($failedDisk);

        try {
            $service->deleteDiskEntity($entity->fresh());
            $this->fail('Ожидалась ошибка удаления');
        } catch (RuntimeException) {
            $this->assertTrue((bool) $entity->fresh()->deleted);
            $this->assertTrue($disk->exists("entities/trash/a/{$entity->id}_doc.txt"));
        }
    }

    public function test_failed_second_file_move_rolls_back_first_file(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $folder = $this->createEntity('/a', 'folder', DiskEntityTypeEnum::FOLDER);
        $disk->put('entities/available/a/folder/one.txt', 'one');
        $disk->put('entities/available/a/folder/two.txt', 'two');
        $disk->put("entities/trash/a/{$folder->id}_folder/keep.txt", 'keep');

        $moveCount = 0;
        $failedDisk = Mockery::mock($disk)->makePartial();
        $failedDisk->shouldReceive('move')->andReturnUsing(function (string $source, string $destination) use ($disk, &$moveCount): bool {
            $moveCount++;

            return $moveCount === 2 ? false : $disk->move($source, $destination);
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($failedDisk);

        try {
            (new FileSystemService)->deleteDiskEntity($folder);
            $this->fail('Ожидалась ошибка второго перемещения');
        } catch (RuntimeException) {
            $this->assertSame(3, $moveCount);
            $this->assertFalse((bool) $folder->fresh()->deleted);
            $this->assertTrue($disk->exists('entities/available/a/folder/one.txt'));
            $this->assertTrue($disk->exists('entities/available/a/folder/two.txt'));
            $this->assertFalse($disk->exists("entities/trash/a/{$folder->id}_folder/one.txt"));
        }
    }

    public function test_missing_trashed_file_does_not_restore_database_record(): void
    {
        Storage::fake('public');
        $entity = DiskEntity::create([
            'path' => '/',
            'original_path' => '/a',
            'title' => 'missing.txt',
            'type' => DiskEntityTypeEnum::FILE->value,
            'deleted' => true,
        ]);

        try {
            (new FileSystemService)->restore($entity);
            $this->fail('Ожидалась ошибка восстановления');
        } catch (RuntimeException) {
            $this->assertTrue((bool) $entity->fresh()->deleted);
        }
    }

    public function test_same_named_files_remain_distinct_in_trash(): void
    {
        Queue::fake();
        Storage::fake('public');
        $service = new FileSystemService;
        $first = $this->createEntity('/a', 'same.txt', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/same.txt', 'first');
        $service->deleteDiskEntity($first);

        $second = $this->createEntity('/a', 'same.txt', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/same.txt', 'second');
        $service->deleteDiskEntity($second);

        Storage::disk('public')->assertExists("entities/trash/a/{$first->id}_same.txt");
        Storage::disk('public')->assertExists("entities/trash/a/{$second->id}_same_{$second->id}.txt");
        $this->assertSame('same_'.$second->id.'.txt', $second->fresh()->title);
    }

    public function test_restore_renames_file_when_active_name_is_taken(): void
    {
        Queue::fake();
        Storage::fake('public');
        $service = new FileSystemService;
        $trashed = $this->createEntity('/a', 'same.txt', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/same.txt', 'old');
        $service->deleteDiskEntity($trashed);

        $active = $this->createEntity('/a', 'same.txt', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/same.txt', 'new');
        $service->restore($trashed->fresh());

        $this->assertSame('new', Storage::disk('public')->get('entities/available/a/same.txt'));
        $this->assertSame('old', Storage::disk('public')->get("entities/available/a/same_{$trashed->id}.txt"));
        $this->assertSame('same_'.$trashed->id.'.txt', $trashed->fresh()->title);
        $this->assertFalse((bool) $trashed->fresh()->deleted);
        $this->assertFalse((bool) $active->fresh()->deleted);
    }

    public function test_restore_preserves_multiple_dots_in_colliding_filename(): void
    {
        Queue::fake();
        Storage::fake('public');
        $service = new FileSystemService;
        $trashed = $this->createEntity('/a', 'archive.tar.gz', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/archive.tar.gz', 'old');
        $service->deleteDiskEntity($trashed);

        $this->createEntity('/a', 'archive.tar.gz', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/archive.tar.gz', 'new');
        $service->restore($trashed->fresh());

        $renamed = "archive.tar_{$trashed->id}.gz";
        $this->assertSame('new', Storage::disk('public')->get('entities/available/a/archive.tar.gz'));
        $this->assertSame('old', Storage::disk('public')->get('entities/available/a/'.$renamed));
        $this->assertSame($renamed, $trashed->fresh()->title);
    }

    public function test_nested_child_can_be_deleted_from_trashed_folder(): void
    {
        Queue::fake();
        Storage::fake('public');
        $folder = $this->createEntity('/a', 'folder', DiskEntityTypeEnum::FOLDER);
        $child = $this->createEntity('/a/folder', 'doc.txt', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/folder/doc.txt', 'document');

        $service = new FileSystemService;
        $service->deleteDiskEntity($folder);
        $service->deleteDiskEntity($child->fresh());

        $this->assertDatabaseMissing('disk_entities', ['id' => $child->id]);
        Storage::disk('public')->assertMissing("entities/trash/a/{$folder->id}_folder/doc.txt");
        $this->assertDatabaseHas('disk_entities', ['id' => $folder->id, 'deleted' => true]);
    }

    public function test_nested_child_can_be_restored_without_restoring_trashed_parent(): void
    {
        Queue::fake();
        Storage::fake('public');
        $folder = $this->createEntity('/a', 'folder', DiskEntityTypeEnum::FOLDER);
        $child = $this->createEntity('/a/folder', 'doc.txt', DiskEntityTypeEnum::FILE);
        Storage::disk('public')->put('entities/available/a/folder/doc.txt', 'document');

        $service = new FileSystemService;
        $service->deleteDiskEntity($folder);
        $service->restore($child->fresh());

        $this->assertFalse((bool) $child->fresh()->deleted);
        $this->assertTrue((bool) $folder->fresh()->deleted);
        Storage::disk('public')->assertExists('entities/available/a/folder/doc.txt');
    }

    private function createEntity(string $path, string $title, DiskEntityTypeEnum $type): DiskEntity
    {
        return DiskEntity::create(['path' => $path, 'title' => $title, 'type' => $type->value]);
    }
}
