<?php

namespace Tests\Feature;

use App\Services\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KnowledgeBaseContentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_contents_keep_order_when_updated(): void
    {
        $service = app(KnowledgeBaseService::class);
        $article = $service->create([
            'title' => 'Article',
            'contents' => [
                ['content' => 'First'],
                ['content' => 'Second'],
            ],
        ]);
        $first = $article->contents()->orderBy('order')->firstOrFail();

        $service->update($article, [
            'contents' => [
                ['id' => $first->id, 'content' => 'Changed'],
                ['content' => 'New'],
            ],
        ]);

        $this->assertSame(['Changed', 'New'], $article->contents()->orderBy('order')->pluck('content')->all());
    }

    public function test_file_content_replaces_media_and_removed_content_is_deleted(): void
    {
        Storage::fake('public');
        $service = app(KnowledgeBaseService::class);
        $article = $service->create([
            'title' => 'Files',
            'contents' => [
                ['file' => UploadedFile::fake()->createWithContent('old.txt', 'old')],
                ['content' => 'Remove me'],
            ],
        ]);
        $fileContent = $article->contents()->where('type', 'file')->firstOrFail();
        $textContent = $article->contents()->where('type', 'text')->firstOrFail();

        $service->update($article, ['contents' => [[
            'id' => $fileContent->id,
            'file' => UploadedFile::fake()->createWithContent('new.txt', 'new'),
        ]]]);

        $this->assertDatabaseMissing('knowledge_base_contents', ['id' => $textContent->id]);
        $this->assertSame('file', $fileContent->fresh()->type);
        $this->assertSame(['new.txt'], $fileContent->fresh()->getMedia('file')->pluck('file_name')->all());
    }
}
