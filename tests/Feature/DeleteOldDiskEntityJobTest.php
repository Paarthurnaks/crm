<?php

namespace Tests\Feature;

use App\Enums\DiskEntityTypeEnum;
use App\Jobs\DeleteOldDiskEntityJob;
use App\Models\DiskEntity;
use App\Services\FileSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DeleteOldDiskEntityJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_serialized_job_resolves_service_and_skips_repeated_run(): void
    {
        $entity = DiskEntity::create([
            'path' => '/',
            'original_path' => '/documents',
            'title' => 'old.txt',
            'type' => DiskEntityTypeEnum::FILE->value,
            'deleted' => true,
        ]);

        $service = Mockery::mock(FileSystemService::class);
        $service->shouldReceive('deleteDiskEntity')
            ->once()
            ->withArgs(fn (DiskEntity $actual): bool => $actual->is($entity))
            ->andReturnUsing(fn () => $entity->delete());
        $this->app->instance(FileSystemService::class, $service);

        $job = unserialize(serialize(new DeleteOldDiskEntityJob($entity->id)));
        $this->app->call([$job, 'handle']);
        $this->app->call([$job, 'handle']);

        $this->assertDatabaseMissing('disk_entities', ['id' => $entity->id]);
    }

    public function test_job_skips_restored_entity(): void
    {
        $entity = DiskEntity::create([
            'path' => '/documents',
            'title' => 'restored.txt',
            'type' => DiskEntityTypeEnum::FILE->value,
            'deleted' => false,
        ]);

        $service = Mockery::mock(FileSystemService::class);
        $service->shouldNotReceive('deleteDiskEntity');
        $this->app->instance(FileSystemService::class, $service);

        $this->app->call([new DeleteOldDiskEntityJob($entity->id), 'handle']);

        $this->assertDatabaseHas('disk_entities', ['id' => $entity->id, 'deleted' => false]);
    }
}
