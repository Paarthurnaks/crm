<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OpenApiCompatibilityTest extends TestCase
{
    public function test_legacy_annotations_and_attributes_generate_api_documentation(): void
    {
        $this->assertSame(0, Artisan::call('l5-swagger:generate'));

        $contents = file_get_contents(storage_path('api-docs/api-docs.json'));
        $this->assertNotFalse($contents);
        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('CRM', $document['info']['title']);
        $this->assertArrayHasKey('/api/login', $document['paths']);
        $this->assertArrayHasKey('/api/deals/', $document['paths']);
        $this->assertArrayHasKey('bearer_token', $document['components']['securitySchemes']);
    }
}
