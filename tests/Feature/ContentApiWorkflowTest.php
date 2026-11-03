<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::withoutEvents(fn (): User => User::create([
            'name' => 'Content', 'surname' => 'Editor',
            'email' => 'content-editor@example.test', 'password' => 'password',
        ])), 'api');
    }

    public function test_document_template_upload_update_and_delete(): void
    {
        $template = UploadedFile::fake()->create(
            'contract.docx', 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $id = $this->postJson('/api/document-templates', [
            'entity' => 'deals', 'title' => 'Contract', 'template' => $template,
        ])->assertOk()->assertJsonPath('data.title', 'Contract')->json('data.id');

        $this->getJson('/api/document-templates/'.$id)->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson('/api/document-templates')->assertOk()->assertJsonPath('data.total', 1);
        $this->putJson('/api/document-templates/'.$id, ['title' => 'Updated contract'])
            ->assertOk()->assertJsonPath('data.title', 'Updated contract');
        $this->postJson('/api/document-templates/'.$id.'/uploadNewTemplate', [])
            ->assertStatus(400);
        $this->deleteJson('/api/document-templates/'.$id)->assertOk();
        $this->assertDatabaseMissing('document_templates', ['id' => $id]);
    }

    public function test_knowledge_base_http_crud_preserves_contents(): void
    {
        $id = $this->postJson('/api/knowledge-bases', [
            'title' => 'How to use CRM',
            'contents' => [['content' => 'Open a deal']],
        ])->assertOk()->assertJsonPath('data.title', 'How to use CRM')->json('data.id');
        $this->getJson('/api/knowledge-bases/'.$id)->assertOk()
            ->assertJsonPath('data.contents.0.content', 'Open a deal');
        $this->getJson('/api/knowledge-bases')->assertOk();
        $this->putJson('/api/knowledge-bases/'.$id, ['title' => 'Revised guide'])
            ->assertOk()->assertJsonPath('data.title', 'Revised guide');
        $this->deleteJson('/api/knowledge-bases/'.$id)->assertOk();
        $this->assertDatabaseMissing('knowledge_bases', ['id' => $id]);
    }
}
