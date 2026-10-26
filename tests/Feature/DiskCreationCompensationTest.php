<?php

namespace Tests\Feature;

use App\Models\DiskEntity;
use App\Models\User;
use App\Services\FileSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiskCreationCompensationTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_is_removed_when_role_attachment_fails(): void
    {
        Storage::fake('public');
        $actor = $this->actorWithoutRole();

        try {
            app(FileSystemService::class)->createFolder(['path' => '/a', 'title' => 'Folder'], $actor);
            $this->fail('Expected role attachment to fail');
        } catch (\Error $exception) {
            $this->assertSame(0, DiskEntity::count());
            $this->assertFalse(Storage::disk('public')->directoryExists('entities/available/a/Folder'));
        }
    }

    public function test_file_is_removed_when_role_attachment_fails(): void
    {
        Storage::fake('public');
        $actor = $this->actorWithoutRole();

        try {
            app(FileSystemService::class)->createFile([
                'path' => '/a',
                'file' => UploadedFile::fake()->create('failed.txt', 1),
            ], $actor);
            $this->fail('Expected role attachment to fail');
        } catch (\Error $exception) {
            $this->assertSame(0, DiskEntity::count());
            $this->assertFalse(Storage::disk('public')->exists('entities/available/a/failed.txt'));
        }
    }

    private function actorWithoutRole(): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'No',
            'surname' => 'Role',
            'email' => 'no-role@example.test',
            'password' => 'password',
        ]));
    }
}
