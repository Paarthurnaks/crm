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
        $role = DB::transaction(function () use ($roleData) {
            $role = Role::create([
                'title' => $roleData['title'],
            ]);

            foreach ($roleData['permissions'] as $permissionData) {
                $this->permissionService->createPermission($permissionData, $role->id);
            }

            $this->syncFieldAccess($role, $roleData, false);
            if (isset($roleData['available_disk_entities'])) {
                $role->availableDiskEntities()->attach($roleData['available_disk_entities']);
            }

            return $role;
        });

        return $role;
    }

    public function updateRole(Role $role, array $roleData): Role
    {
        DB::transaction(function () use ($role, $roleData) {
            if (array_key_exists('title', $roleData)) {
                $role->update(['title' => $roleData['title']]);
            }
            if (isset($roleData['permissions'])) {
                foreach ($roleData['permissions'] as $permissionData) {
                    if (array_key_exists('id', $permissionData)) {
                        $this->permissionService->updatePermission($permissionData, $role->id);
                    } else {
                        $this->permissionService->createPermission($permissionData, $role->id);
                    }
                }
            }

            if (isset($roleData['available_disk_entities'])) {
                $role->availableDiskEntities()->detach();
                $role->availableDiskEntities()->attach($roleData['available_disk_entities']);
            }

            $this->syncFieldAccess($role, $roleData, true);
        });

        return $role;
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
    }

    private function syncRoleRelation(Role $role, string $relation, string $action, array $ids, bool $replace): void
    {
        if ($replace) {
            $role->{$relation}()->wherePivot('type', $action)->detach();
        }

        $role->{$relation}()->attach($ids, ['type' => $action]);
    }
}
