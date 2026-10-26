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
