<?php

namespace Tests\Feature;

use App\Enums\DefaultRolesEnum;
use App\Models\Field;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleFieldSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_view_fields_are_copied_to_client_and_empty_update_removes_them(): void
    {
        $partner = Role::create(['title' => 'Partner', 'original_title' => DefaultRolesEnum::PARTNER->value]);
        $client = Role::create(['title' => 'Client', 'original_title' => DefaultRolesEnum::CLIENT->value]);
        $field = Field::create(['name' => 'Title', 'entity' => 'deals', 'type' => 'string', 'code' => 'title']);
        $service = app(RoleService::class);

        $service->updateRole($partner, ['available_view_fields' => [$field->id]]);
        $this->assertSame([$field->id], $partner->fieldsView()->pluck('fields.id')->all());
        $this->assertSame([$field->id], $client->fieldsView()->pluck('fields.id')->all());

        $service->updateRole($partner, ['available_view_fields' => []]);
        $this->assertSame([], $partner->fieldsView()->pluck('fields.id')->all());
        $this->assertSame([], $client->fieldsView()->pluck('fields.id')->all());
    }

    public function test_missing_field_key_keeps_existing_role_links(): void
    {
        $role = Role::create(['title' => 'Staff']);
        $field = Field::create(['name' => 'Title', 'entity' => 'deals', 'type' => 'string', 'code' => 'title']);
        $role->fields()->attach($field->id, ['type' => 'view']);

        app(RoleService::class)->updateRole($role, ['title' => 'Renamed']);

        $this->assertSame([$field->id], $role->fieldsView()->pluck('fields.id')->all());
    }
}
