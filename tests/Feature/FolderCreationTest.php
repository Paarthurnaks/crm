<?php

namespace Tests\Feature;

use App\Models\DiskEntity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FolderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_endpoint_returns_bad_request_for_missing_entity(): void
    {
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'missing-disk-entity@example.test',
            'password' => 'unused',
        ]));

        $this->actingAs($user, 'api')
            ->deleteJson('/api/file-system/delete-disk-entity?disk_entity_id=999999')
            ->assertBadRequest();
    }

    public function test_delete_and_restore_require_entity_id(): void
    {
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'disk-id-required@example.test',
            'password' => 'unused',
        ]));

        $this->actingAs($user, 'api')
            ->deleteJson('/api/file-system/delete-disk-entity')
            ->assertUnprocessable();
        $this->actingAs($user, 'api')
            ->postJson('/api/file-system/return-disk-entity')
            ->assertUnprocessable();
        $this->actingAs($user, 'api')
            ->postJson('/api/file-system/return-disk-entity', ['disk_entity_id' => 999999])
            ->assertNotFound();
    }

    public function test_folder_is_created_with_zero_size(): void
    {
        Storage::fake('public');
        $role = Role::create(['title' => 'test']);
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'folder-test@example.test',
            'password' => 'password',
            'role_id' => $role->id,
        ]));

        $this->actingAs($user, 'api')
            ->postJson('/api/file-system/store-folder', ['path' => '/a', 'title' => 'Folder'])
            ->assertOk();

        $this->assertTrue(Storage::disk('public')->directoryExists('entities/available/a/Folder'));
        $this->assertSame(0, DiskEntity::where('title', 'Folder')->value('size'));
    }

    public function test_root_folder_and_file_share_the_same_parent_path(): void
    {
        Storage::fake('public');
        $role = Role::create(['title' => 'root-path-test']);
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'root-path@example.test',
            'password' => 'unused',
            'role_id' => $role->id,
        ]));

        $this->actingAs($user, 'api')
            ->postJson('/api/file-system/store-folder', ['path' => '/', 'title' => 'Folder'])
            ->assertOk();
        $this->actingAs($user, 'api')
            ->postJson('/api/file-system/store-folder', ['path' => '/', 'title' => 'Folder'])
            ->assertUnprocessable();
        $this->actingAs($user, 'api')
            ->postJson('/api/file-system/store-file', [
                'path' => '/',
                'file' => UploadedFile::fake()->create('root.txt', 1),
            ])
            ->assertOk();

        Storage::disk('public')->assertExists('entities/available/root.txt');
        $this->assertTrue(Storage::disk('public')->directoryExists('entities/available/Folder'));
        $this->assertSame(2, DiskEntity::where('path', '/')->count());
    }

    public function test_nested_parent_path_is_stored_without_trailing_separator(): void
    {
        Storage::fake('public');
        $role = Role::create(['title' => 'nested-path-test']);
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'nested-path@example.test',
            'password' => 'unused',
            'role_id' => $role->id,
        ]));

        $this->actingAs($user, 'api')
            ->postJson('/api/file-system/store-folder', ['path' => '/a/nested/', 'title' => 'Folder with spaces'])
            ->assertOk();

        $this->assertTrue(Storage::disk('public')->directoryExists('entities/available/a/nested/Folder with spaces'));
        $this->assertSame('/a/nested', DiskEntity::where('title', 'Folder with spaces')->value('path'));
    }

    public function test_file_upload_returns_disk_entity_and_stores_file(): void
    {
        Storage::fake('public');
        $role = Role::create(['title' => 'test']);
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'file-test@example.test',
            'password' => 'password',
            'role_id' => $role->id,
        ]));

        $this->actingAs($user, 'api')
            ->postJson('/api/file-system/store-file', [
                'path' => '/a',
                'file' => UploadedFile::fake()->create('test.txt', 1),
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'test.txt');

        $this->assertTrue(Storage::disk('public')->exists('entities/available/a/test.txt'));
        $this->assertSame(1, DiskEntity::where('title', 'test.txt')->count());
    }
}
