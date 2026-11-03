<?php

namespace Tests\Feature;

use App\Models\DiskEntity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileSystemApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_lifecycle_uses_api_and_rejects_duplicate_name(): void
    {
        Queue::fake();
        Storage::fake('public');
        $this->actingAs($this->user(), 'api');

        $folderId = $this->postJson('/api/file-system/store-folder', [
            'title' => 'Shared folder', 'path' => '/',
        ])->assertOk()->assertJsonPath('data.title', 'Shared folder')->json('data.id');
        $this->assertDatabaseHas('disk_entities', ['id' => $folderId, 'deleted' => false]);
        $this->postJson('/api/file-system/store-folder', [
            'title' => 'Shared folder', 'path' => '/',
        ])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->getJson('/api/file-system/content')->assertOk()->assertJsonPath('data.total', 1);

        $this->deleteJson('/api/file-system/delete-disk-entity', ['disk_entity_id' => $folderId])
            ->assertOk();
        $this->assertTrue(DiskEntity::findOrFail($folderId)->deleted);
        $this->postJson('/api/file-system/return-disk-entity', ['disk_entity_id' => $folderId])
            ->assertOk();
        $this->assertFalse(DiskEntity::findOrFail($folderId)->deleted);
    }

    public function test_vendor_file_manager_routes_require_authentication(): void
    {
        foreach (['initialize', 'content', 'tree', 'download'] as $endpoint) {
            $this->getJson('/api/file-manager/'.$endpoint)->assertUnauthorized();
        }

        $this->actingAs($this->user(), 'api')
            ->getJson('/api/file-manager/initialize')->assertOk();
    }

    private function user(): User
    {
        $role = Role::create(['title' => 'Disk role']);

        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Disk', 'surname' => 'Tester',
            'email' => fake()->unique()->safeEmail(), 'password' => 'password',
            'role_id' => $role->id,
        ]));
    }
}
