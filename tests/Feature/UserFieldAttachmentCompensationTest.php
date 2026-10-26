<?php

namespace Tests\Feature;

use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\UserFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class UserFieldAttachmentCompensationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_attachment_is_removed_when_second_upload_fails(): void
    {
        Storage::fake('public');
        $field = UserField::create(['name' => 'File', 'entity' => 'deals', 'type' => 'file', 'code' => 'file']);

        try {
            app(UserFieldService::class)->addAttachments(42, $field->id, [
                UploadedFile::fake()->create('first.txt', 1),
                null,
            ]);
            $this->fail('Expected the second attachment to fail');
        } catch (\TypeError $exception) {
            $this->assertSame(0, UserFieldValue::count());
            $this->assertSame(0, Media::count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }
}
