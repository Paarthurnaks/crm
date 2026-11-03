<?php

namespace Tests\Feature;

use App\Enums\UserFieldTypeEnum;
use App\Models\Section;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFieldApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_enum_field_and_section_crud_persist_ordered_values(): void
    {
        $actor = $this->user();
        $this->actingAs($actor, 'api');

        $created = $this->postJson('/api/user-fields', [
            'entity' => 'deals', 'name' => 'Priority', 'type' => UserFieldTypeEnum::ENUM->value,
            'options' => [
                ['option' => 'High', 'sort_order' => 1],
                ['option' => 'Low', 'sort_order' => 2],
            ],
        ])->assertCreated()->assertJsonPath('data.name', 'Priority');
        $fieldId = $created->json('data.id');
        $this->assertSame(2, UserField::findOrFail($fieldId)->userFieldEnumValues()->count());
        $this->getJson('/api/user-fields/'.$fieldId)->assertOk()->assertJsonPath('data.id', $fieldId);
        $this->getJson('/api/user-fields')->assertOk()->assertJsonPath('data.total', 1);

        $sectionId = $this->postJson('/api/sections', [
            'entity' => 'deals', 'title' => 'Custom', 'position' => 2,
            'section_fields' => [['user_field_id' => $fieldId, 'position' => 1]],
        ])->assertCreated()->assertJsonPath('data.title', 'Custom')->json('data.id');
        $this->assertDatabaseHas('sections', ['id' => $sectionId, 'user_id' => $actor->id]);
        $this->assertSame(1, Section::findOrFail($sectionId)->fields()->count());

        $this->putJson('/api/sections/'.$sectionId, ['title' => 'Renamed', 'position' => 3])
            ->assertOk()->assertJsonPath('data.title', 'Renamed');
        $this->putJson('/api/user-fields/'.$fieldId, ['name' => 'Urgency'])
            ->assertOk()->assertJsonPath('data.name', 'Urgency');
        $this->deleteJson('/api/sections/'.$sectionId)->assertOk();
        $this->deleteJson('/api/user-fields/'.$fieldId)->assertOk();
        $this->assertDatabaseMissing('sections', ['id' => $sectionId]);
        $this->assertDatabaseMissing('user_fields', ['id' => $fieldId]);
    }

    public function test_invalid_enum_field_is_not_created(): void
    {
        $this->actingAs($this->user(), 'api');

        $this->postJson('/api/user-fields', [
            'entity' => 'deals', 'name' => 'Priority', 'type' => UserFieldTypeEnum::ENUM->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('options');
        $this->assertSame(0, UserField::count());
    }

    private function user(): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Field', 'surname' => 'Tester',
            'email' => fake()->unique()->safeEmail(), 'password' => 'password',
        ]));
    }
}
