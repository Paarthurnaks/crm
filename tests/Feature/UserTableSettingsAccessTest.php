<?php

namespace Tests\Feature;

use App\Enums\DefaultRolesEnum;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserTableSettings;
use App\Services\UserTableSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTableSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_columns_and_filters_by_correct_field_type(): void
    {
        $role = Role::create(['title' => 'limited']);
        $user = $this->createUser($role);
        $field = Field::create(['name' => 'Title', 'entity' => 'deals', 'type' => 'string', 'code' => 'title']);
        $userField = UserField::create(['name' => 'Custom', 'entity' => 'deals', 'type' => 'string', 'code' => 'custom']);
        $role->fields()->attach($field->id, ['type' => 'view']);
        $role->userFields()->attach($userField->id, ['type' => 'view']);
        UserTableSettings::create([
            'user_id' => $user->id,
            'entity' => 'deals',
            'settings' => [
                'table_columns' => [
                    ['id' => $field->id, 'is_userfield' => false],
                    ['id' => $userField->id, 'is_userfield' => true],
                ],
                'filters' => [['id' => $userField->id, 'is_userfield' => true]],
            ],
        ]);
        $this->actingAs($user, 'api');

        $settings = (new UserTableSettingsService)->getUserTableSettings(['entity' => 'deals'])->settings;

        $this->assertCount(2, $settings['table_columns']);
        $this->assertCount(1, $settings['filters']);
        $this->assertSame($userField->id, $settings['filters'][0]['id']);
    }

    public function test_default_template_is_selected_by_super_admin_role(): void
    {
        $adminRole = Role::create(['title' => 'admin', 'original_title' => DefaultRolesEnum::SUPER_ADMIN->value]);
        $admin = $this->createUser($adminRole);
        $clientRole = Role::create(['title' => 'limited']);
        $client = $this->createUser($clientRole);
        $field = Field::create(['name' => 'Title', 'entity' => 'deals', 'type' => 'string', 'code' => 'title']);
        $clientRole->fields()->attach($field->id, ['type' => 'view']);
        UserTableSettings::create([
            'user_id' => $admin->id,
            'entity' => 'deals',
            'settings' => ['table_columns' => [['id' => $field->id, 'is_userfield' => false]]],
        ]);
        $this->actingAs($client, 'api');

        $settings = (new UserTableSettingsService)->getUserTableSettings(['entity' => 'deals']);

        $this->assertSame($client->id, $settings->user_id);
        $this->assertCount(1, $settings->settings['table_columns']);
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
