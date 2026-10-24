<?php

namespace Tests\Feature;

use App\Enums\DefaultRolesEnum;
use App\Http\Resources\SectionResource;
use App\Models\Field;
use App\Models\Role;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SectionFieldVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_resource_only_returns_fields_allowed_for_requested_action(): void
    {
        $role = Role::create(['title' => 'limited']);
        $user = $this->createUser($role);
        $section = Section::create(['entity' => 'deals', 'title' => 'Main', 'user_id' => $user->id, 'position' => 1]);
        $visibleField = Field::create(['name' => 'Title', 'entity' => 'deals', 'type' => 'string', 'code' => 'title']);
        $hiddenField = Field::create(['name' => 'Note', 'entity' => 'deals', 'type' => 'string', 'code' => 'note']);
        $visibleSectionField = SectionField::create(['section_id' => $section->id, 'field_id' => $visibleField->id, 'position' => 1]);
        SectionField::create(['section_id' => $section->id, 'field_id' => $hiddenField->id, 'position' => 2]);
        $role->fields()->attach($visibleField->id, ['type' => 'view']);

        $request = Request::create('/api/sections', 'GET');
        $request->setUserResolver(fn (): User => $user);
        $fields = (new SectionResource($section, 'view'))->toArray($request)['fields'];

        $this->assertCount(1, $fields);
        $this->assertSame($visibleSectionField->id, $fields->first()->resource->id);
        $this->assertFalse($fields->first()->toArray($request)['is_editable']);
    }

    public function test_super_admin_can_view_fields_without_field_pivot_rows(): void
    {
        $role = Role::create(['title' => 'admin', 'original_title' => DefaultRolesEnum::SUPER_ADMIN->value]);
        $user = $this->createUser($role);
        $section = Section::create(['entity' => 'deals', 'title' => 'Main', 'user_id' => $user->id, 'position' => 1]);
        $field = Field::create(['name' => 'Title', 'entity' => 'deals', 'type' => 'string', 'code' => 'title']);
        SectionField::create(['section_id' => $section->id, 'field_id' => $field->id, 'position' => 1]);

        $request = Request::create('/api/sections', 'GET');
        $request->setUserResolver(fn (): User => $user);

        $this->assertCount(1, (new SectionResource($section, 'view'))->toArray($request)['fields']);
    }

    public function test_create_view_excludes_prohibited_fields_even_when_role_pivot_exists(): void
    {
        $role = Role::create(['title' => 'creator']);
        $user = $this->createUser($role);
        $section = Section::create(['entity' => 'deals', 'title' => 'Main', 'user_id' => $user->id, 'position' => 1]);
        $field = Field::create(['name' => 'Creator', 'entity' => 'deals', 'type' => 'user', 'code' => 'creator']);
        SectionField::create(['section_id' => $section->id, 'field_id' => $field->id, 'position' => 1]);
        $role->fields()->attach($field->id, ['type' => 'create']);

        $request = Request::create('/api/sections', 'GET');
        $request->setUserResolver(fn (): User => $user);

        $this->assertCount(0, (new SectionResource($section, 'create'))->toArray($request)['fields']);
    }

    private function createUser(Role $role): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
        ]));
    }
}
