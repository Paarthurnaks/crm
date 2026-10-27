<?php

namespace App\Services;

use App\Models\Permission;

class PermissionService
{
    /**
     * @param array
     * @param int
     */
    public function createPermission(array $permissionData, int $roleId): void
    {
        Permission::create([
            'entity' => $permissionData['entity'],
            'role_id' => $roleId,
            'view' => $permissionData['view'],
            'create' => $permissionData['create'],
            'update' => $permissionData['update'],
            'delete' => $permissionData['delete'],
            'import' => $permissionData['import'] ?? null,
            'export' => $permissionData['export'] ?? null,
        ]);
    }

    /**
     * @param array
     */
    public function updatePermission(array $permissionData, int $roleId): void
    {
        $permission = Permission::where('role_id', $roleId)->findOrFail($permissionData['id']);
        unset($permissionData['id']); // id не обновляем, поэтому убираем из объекта
        $permission->update($permissionData);
    }
}
