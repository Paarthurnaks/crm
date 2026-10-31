<?php

namespace App\Services;

use App\Enums\DefaultRolesEnum;
use App\Http\Filters\RoleFilter;
use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleService
{
    public function __construct(private PermissionService $permissionService) {}

    public function getRoles(RoleFilter $filter, array $queryParams): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        return Role::filter($filter)->paginate($limit);
    }

    public function createRole(array $roleData): Role
    {
        return DB::transaction(function () use ($roleData) {
            $role = Role::create([
                'title' => $roleData['title'],
            ]);

            $this->syncPermissions($role, $roleData['permissions'], false);

            $this->syncFieldAccess($role, $roleData, false);
            if (isset($roleData['available_disk_entities'])) {
                $role->availableDiskEntities()->attach($roleData['available_disk_entities']);
            }

            return $role;
        });
    }

    public function updateRole(Role $role, array $roleData): Role
    {
        DB::transaction(function () use ($role, $roleData) {
            if (array_key_exists('title', $roleData)) {
                $role->update(['title' => $roleData['title']]);
            }
            if (isset($roleData['permissions'])) {
                $this->syncPermissions($role, $roleData['permissions'], true);
            }

            if (isset($roleData['available_disk_entities'])) {
                $role->availableDiskEntities()->detach();
                $role->availableDiskEntities()->attach($roleData['available_disk_entities']);
            }

            $this->syncFieldAccess($role, $roleData, true);
        });

        return $role;
    }

    private function syncPermissions(Role $role, array $permissions, bool $updating): void
    {
        foreach ($permissions as $permissionData) {
            if ($updating && array_key_exists('id', $permissionData)) {
                $this->permissionService->updatePermission($permissionData, $role->id);
            } else {
                $this->permissionService->createPermission($permissionData, $role->id);
            }
        }
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();
    }

    private function syncFieldAccess(Role $role, array $data, bool $replace): void
    {
        $clientRole = $replace && $role->original_title === DefaultRolesEnum::PARTNER->value
            ? Role::where('original_title', DefaultRolesEnum::CLIENT->value)->first()
            : null;

        foreach (['view', 'create', 'update'] as $action) {
            $this->syncFieldAction($role, $clientRole, $data, $action, $replace);
        }
    }

    private function syncFieldAction(Role $role, ?Role $clientRole, array $data, string $action, bool $replace): void
    {
        foreach (['fields' => 'fields', 'user_fields' => 'userFields'] as $suffix => $relation) {
            $key = "available_{$action}_{$suffix}";
            if (! isset($data[$key])) {
                continue;
            }

            $this->syncRoleRelation($role, $relation, $action, $data[$key], $replace);
            if ($action === 'view' && $clientRole) {
                $this->syncRoleRelation($clientRole, $relation, $action, $data[$key], true);
            }
            Log::debug('role.field_access.synced', [
                'role_id' => $role->id,
                'action' => $action,
                'field_count' => count($data[$key]),
            ]);
        }
    }

    private function syncRoleRelation(Role $role, string $relation, string $action, array $ids, bool $replace): void
    {
        if ($replace) {
            $role->{$relation}()->wherePivot('type', $action)->detach();
        }

        $role->{$relation}()->attach($ids, ['type' => $action]);
    }
}
