<?php

namespace Tests\Feature;

use App\Enums\PermissionTypeEnum;
use App\Models\PartnerClient;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourcePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_routes_require_authentication(): void
    {
        foreach (['/api/users', '/api/roles', '/api/deals', '/api/reports'] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }
    }

    public function test_forbidden_user_permission_blocks_reads_and_writes(): void
    {
        $role = Role::create(['title' => 'No user access']);
        $actor = $this->createUser($role);
        Permission::create([
            'role_id' => $role->id,
            'entity' => 'users',
            'view' => PermissionTypeEnum::FORBIDDEN->value,
            'create' => PermissionTypeEnum::FORBIDDEN->value,
            'update' => PermissionTypeEnum::FORBIDDEN->value,
            'delete' => PermissionTypeEnum::FORBIDDEN->value,
        ]);

        $this->actingAs($actor, 'api')->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [
            'name' => 'Forbidden', 'surname' => 'User', 'email' => 'forbidden@example.test',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'forbidden@example.test']);
    }

    public function test_partner_can_view_only_linked_clients(): void
    {
        $partnerRole = Role::create(['title' => 'Partner test', 'original_title' => 'Partner']);
        $clientRole = Role::create(['title' => 'Client test', 'original_title' => 'Client']);
        Permission::create([
            'role_id' => $partnerRole->id,
            'entity' => 'clients',
            'view' => PermissionTypeEnum::OWN->value,
            'create' => PermissionTypeEnum::FORBIDDEN->value,
            'update' => PermissionTypeEnum::FORBIDDEN->value,
            'delete' => PermissionTypeEnum::FORBIDDEN->value,
        ]);
        $partner = $this->createUser($partnerRole);
        $linked = $this->createUser($clientRole);
        $other = $this->createUser($clientRole);
        PartnerClient::create(['partner_id' => $partner->id, 'client_id' => $linked->id]);

        $this->actingAs($partner, 'api')
            ->getJson('/api/users/'.$linked->id)
            ->assertOk()
            ->assertJsonPath('data.id', $linked->id);
        $this->getJson('/api/users/'.$other->id)->assertForbidden();
    }

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

        $this->assertSame(PermissionTypeEnum::ALL->value, $user->permissionScope('users', 'view'));
        $this->actingAs($user, 'api')
            ->getJson('/api/roles')
            ->assertOk();
    }

    public function test_authorized_user_can_manage_role_without_optional_permissions(): void
    {
        $role = Role::create(['title' => 'Role administrator']);
        $actor = $this->createUser($role);
        Permission::create([
            'role_id' => $role->id, 'entity' => 'users',
            'view' => PermissionTypeEnum::ALL->value,
            'create' => PermissionTypeEnum::ALL->value,
            'update' => PermissionTypeEnum::ALL->value,
            'delete' => PermissionTypeEnum::ALL->value,
        ]);
        $this->actingAs($actor, 'api');

        $id = $this->postJson('/api/roles', ['title' => 'New role'])
            ->assertCreated()->assertJsonPath('data.title', 'New role')->json('data.id');
        $this->getJson('/api/roles/'.$id)->assertOk()->assertJsonPath('data.id', $id);
        $this->putJson('/api/roles/'.$id, ['title' => 'Renamed role'])
            ->assertOk()->assertJsonPath('data.title', 'Renamed role');
        $this->deleteJson('/api/roles/'.$id)->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $id]);
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

    public function test_permission_update_cannot_target_another_role(): void
    {
        $owner = Role::create(['title' => 'Owner']);
        $other = Role::create(['title' => 'Other']);
        $permission = Permission::create([
            'role_id' => $other->id,
            'entity' => 'users',
            'view' => PermissionTypeEnum::ALL->value,
            'create' => PermissionTypeEnum::ALL->value,
            'update' => PermissionTypeEnum::ALL->value,
            'delete' => PermissionTypeEnum::ALL->value,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(PermissionService::class)->updatePermission([
            'id' => $permission->id,
            'view' => PermissionTypeEnum::FORBIDDEN->value,
        ], $owner->id);
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
