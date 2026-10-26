<?php

namespace Tests\Feature;

use App\Models\Field;
use App\Models\Role;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use App\Services\FieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAccessConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_list_and_section_relation_use_the_same_action_permissions(): void
    {
        $role = Role::create(['title' => 'limited']);
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User', 'email' => 'field-access@example.test',
            'password' => 'password', 'role_id' => $role->id,
        ]));
        $viewField = Field::create(['name' => 'View', 'entity' => 'deals', 'type' => 'string', 'code' => 'view_field']);
        $updateField = Field::create(['name' => 'Update', 'entity' => 'deals', 'type' => 'string', 'code' => 'update_field']);
        $role->fields()->attach($viewField->id, ['type' => 'view']);
        $role->fields()->attach($updateField->id, ['type' => 'update']);
        $section = Section::create(['entity' => 'deals', 'title' => 'Main', 'user_id' => $user->id, 'position' => 1]);
        SectionField::create(['section_id' => $section->id, 'field_id' => $viewField->id, 'position' => 1]);
        SectionField::create(['section_id' => $section->id, 'field_id' => $updateField->id, 'position' => 2]);
        $this->actingAs($user);

        $service = app(FieldService::class);
        $this->assertSame([$viewField->id], $service->getFieldsAndUserFields('deals', ['type' => 'view'])['fields']->pluck('id')->all());
        $this->assertSame([$updateField->id], $service->getFieldsAndUserFields('deals', ['type' => 'update'])['fields']->pluck('id')->all());
        $this->assertSame([], $service->getFieldsAndUserFields('deals', ['type' => 'create'])['fields']->pluck('id')->all());
        $this->assertSame([$viewField->id], $section->availableFieldsView()->pluck('field_id')->all());
        $this->assertSame([$updateField->id], $section->availableFieldsUpdate()->pluck('field_id')->all());
    }
}
