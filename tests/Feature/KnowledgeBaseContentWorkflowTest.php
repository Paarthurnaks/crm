<?php

namespace Tests\Feature;

use App\Services\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
