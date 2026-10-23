<?php

namespace App\Services;

use App\Enums\DefaultRolesEnum;
use App\Http\Filters\RoleFilter;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(private PermissionService $permissionService) {}

    /**
     * @return Collection
     */
    public function getRoles(RoleFilter $filter, array $queryParams): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        return Role::filter($filter)->paginate($limit);
    }

    /**
     * @param array
     */
    public function createRole(array $roleData): Role
    {
        $role = DB::transaction(function () use ($roleData) {
            $role = Role::create([
                'title' => $roleData['title'],
            ]);

            foreach ($roleData['permissions'] as $permissionData) {
                $this->permissionService->createPermission($permissionData, $role->id);
            }

            if (isset($roleData['available_view_fields'])) {
                $role->fields()->attach($roleData['available_view_fields'], ['type' => 'view']);
            }
            if (isset($roleData['available_view_user_fields'])) {
                $role->userFields()->attach($roleData['available_view_user_fields'], ['type' => 'view']);
            }
            if (isset($roleData['available_create_fields'])) {
                $role->fields()->attach($roleData['available_create_fields'], ['type' => 'create']);
            }
            if (isset($roleData['available_create_user_fields'])) {
                $role->userFields()->attach($roleData['available_create_user_fields'], ['type' => 'create']);
            }
            if (isset($roleData['available_update_fields'])) {
                $role->fields()->attach($roleData['available_update_fields'], ['type' => 'update']);
            }
            if (isset($roleData['available_update_user_fields'])) {
                $role->userFields()->attach($roleData['available_update_user_fields'], ['type' => 'update']);
            }
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
                    array_key_exists('id', $permissionData) ? $this->permissionService->updatePermission($permissionData) : $this->permissionService->createPermission($permissionData, $role->id);
                }
            }

            if (isset($roleData['available_disk_entities'])) {
                $role->availableDiskEntities()->detach();
                $role->availableDiskEntities()->attach($roleData['available_disk_entities']);
            }

            if (isset($roleData['available_view_fields'])) {
                $role->fields()->wherePivot('type', 'view')->detach();
                $role->fields()->attach($roleData['available_view_fields'], ['type' => 'view']);

                if ($role->original_title === DefaultRolesEnum::PARTNER->value) {
                    $clientRole = Role::where('original_title', DefaultRolesEnum::CLIENT->value)->first();

                    $clientRole->fields()->wherePivot('type', 'view')->detach();
                    $clientRole->fields()->attach($roleData['available_view_fields'], ['type' => 'view']);
                }
            }
            if (isset($roleData['available_view_user_fields'])) {
                $role->userFields()->wherePivot('type', 'view')->detach();
                $role->userFields()->attach($roleData['available_view_user_fields'], ['type' => 'view']);

                if ($role->original_title === DefaultRolesEnum::PARTNER->value) {
                    $clientRole = Role::where('original_title', DefaultRolesEnum::CLIENT->value)->first();

                    $clientRole->userFields()->wherePivot('type', 'view')->detach();
                    $clientRole->userFields()->attach($roleData['available_view_user_fields'], ['type' => 'view']);
                }
            }

            if (isset($roleData['available_create_fields'])) {
                $role->fields()->wherePivot('type', 'create')->detach();
                $role->fields()->attach($roleData['available_create_fields'], ['type' => 'create']);
            }
            if (isset($roleData['available_create_user_fields'])) {
                $role->userFields()->wherePivot('type', 'create')->detach();
                $role->userFields()->attach($roleData['available_create_user_fields'], ['type' => 'create']);
            }

            if (isset($roleData['available_update_fields'])) {
                $role->fields()->wherePivot('type', 'update')->detach();
                $role->fields()->attach($roleData['available_update_fields'], ['type' => 'update']);
            }
            if (isset($roleData['available_update_user_fields'])) {
                $role->userFields()->wherePivot('type', 'update')->detach();
                $role->userFields()->attach($roleData['available_update_user_fields'], ['type' => 'update']);
            }
        });

        return $role;
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();
    }
}
