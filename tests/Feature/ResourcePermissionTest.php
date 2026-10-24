<?php

namespace Tests\Feature;

use App\Enums\PermissionTypeEnum;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourcePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_role_cannot_access_roles_resource(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'api')
            ->getJson('/api/roles')
            ->assertForbidden();
    }

    public function test_forbidden_permission_blocks_roles_resource(): void
    {
        $role = Role::create(['title' => 'restricted']);
        $user = $this->createUser($role);
        Permission::create([
            'role_id' => $role->id,
            'entity' => 'users',
            'view' => PermissionTypeEnum::FORBIDDEN->value,
            'create' => PermissionTypeEnum::FORBIDDEN->value,
            'update' => PermissionTypeEnum::FORBIDDEN->value,
            'delete' => PermissionTypeEnum::FORBIDDEN->value,
        ]);

        $this->actingAs($user, 'api')
            ->getJson('/api/roles')
            ->assertForbidden();
    }

    public function test_all_permission_allows_roles_resource(): void
    {
        $role = Role::create(['title' => 'administrator']);
        $user = $this->createUser($role);
        Permission::create([
            'role_id' => $role->id,
            'entity' => 'users',
            'view' => PermissionTypeEnum::ALL->value,
            'create' => PermissionTypeEnum::ALL->value,
            'update' => PermissionTypeEnum::ALL->value,
            'delete' => PermissionTypeEnum::ALL->value,
        ]);

        $this->actingAs($user, 'api')
            ->getJson('/api/roles')
            ->assertOk();
    }

    public function test_report_access_uses_user_ids_and_keeps_public_reports_visible(): void
    {
        $author = $this->createUser();
        $allowedUser = $this->createUser();
        $otherUser = $this->createUser();
        $report = Report::create([
            'title' => 'Access report',
            'period_from' => '2024-01-01 00:00:00',
            'period_to' => '2024-12-31 23:59:59',
            'filter' => [],
            'author_id' => $author->id,
        ]);

        $this->assertTrue($report->canBeViewedBy($otherUser->id));

        $report->userAccess()->attach($allowedUser->id);

        $this->assertTrue($report->canBeViewedBy($author->id));
        $this->assertTrue($report->canBeViewedBy($allowedUser->id));
        $this->assertFalse($report->canBeViewedBy($otherUser->id));
    }

    private function createUser(?Role $role = null): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role?->id,
        ]));
    }
}
