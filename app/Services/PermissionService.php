<?php

namespace App\Services;

use App\Models\Permission;

class PermissionService
{   
    /**
     * @param array
     * @param int
     * @return void
     */
    public function createPermission(array $permissionData, int $role_id): void
    {
        Permission::create([
            "entity" => $permissionData["entity"],
            "role_id" => $role_id,
            "view" => $permissionData["view"],
            "create" => $permissionData["create"],
            "update" => $permissionData["update"],
            "delete" => $permissionData["delete"],
            "import" => $permissionData["import"] ?? null,
            "export" => $permissionData["export"] ?? null
        ]);
    }

    /**
     * @param array
     * @return void
     */
    public function updatePermission(array $permissionData): void
    {
        $permission = Permission::find($permissionData["id"]);
        unset($permissionData["id"]); // id не обновляем, поэтому убираем из объекта
        $permission->update($permissionData);
    }
}