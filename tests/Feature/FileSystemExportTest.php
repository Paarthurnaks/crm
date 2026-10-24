<?php

namespace Tests\Feature;

use App\Enums\DiskEntityTypeEnum;
use App\Models\DiskEntity;
use App\Services\FileSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class FileSystemExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_does_not_include_sibling_with_same_path_prefix(): void
    {
        Storage::fake('public');
        DiskEntity::create(['path' => '/a', 'title' => 'one.txt', 'type' => DiskEntityTypeEnum::FILE->value]);
        DiskEntity::create(['path' => '/ab', 'title' => 'two.txt', 'type' => DiskEntityTypeEnum::FILE->value]);
        Storage::disk('public')->put('entities/available/a/one.txt', 'one');
        Storage::disk('public')->put('entities/available/ab/two.txt', 'two');

        $zipPath = (new FileSystemService)->createExportedZip('/a');

        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($zipPath));
            $this->assertNotFalse($zip->locateName('one.txt'));
            $this->assertFalse($zip->locateName('two.txt'));
            $zip->close();
        } finally {
            File::delete($zipPath);
        }
    }

    public function test_export_rejects_parent_directory_segments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FileSystemService)->createExportedZip('/a/../b');
    }

    public function test_export_fails_when_database_entry_has_no_file(): void
    {
        Storage::fake('public');
        DiskEntity::create(['path' => '/a', 'title' => 'missing.txt', 'type' => DiskEntityTypeEnum::FILE->value]);

        $this->expectException(RuntimeException::class);

        (new FileSystemService)->createExportedZip('/a');
    }
}
