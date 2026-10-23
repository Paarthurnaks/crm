<?php

namespace Database\Seeders;

use App\Enums\DefaultRolesEnum;
use App\Enums\PermissionTypeEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $role = Role::updateOrCreate([
                'title' => DefaultRolesEnum::SUPER_ADMIN->label(),
            ], [
                'original_title' => DefaultRolesEnum::SUPER_ADMIN->value,
            ]);

            $entities = ['deals', 'users', 'tasks', 'analytics', 'storage', 'settings', 'helpdesk', 'partners_chats'];

            foreach ($entities as $entity) {
                Permission::updateOrCreate(
                    [
                        'entity' => $entity,
                        'role_id' => $role->id,
                    ],
                    [
                        'view' => PermissionTypeEnum::ALL,
                        'create' => PermissionTypeEnum::ALL,
                        'update' => PermissionTypeEnum::ALL,
                        'delete' => PermissionTypeEnum::ALL,
                        'import' => PermissionTypeEnum::ALL,
                        'export' => PermissionTypeEnum::ALL,
                    ]
                );
            }

            $role = Role::updateOrCreate([
                'title' => DefaultRolesEnum::PARTNER->label(),
            ], [
                'original_title' => DefaultRolesEnum::PARTNER->value,
            ]);

            $entities = ['deals', 'docket', 'clients'];

            foreach ($entities as $entity) {
                Permission::updateOrCreate(
                    [
                        'entity' => $entity,
                        'role_id' => $role->id,
                    ],
                    [
                        'view' => PermissionTypeEnum::OWN,
                        'create' => PermissionTypeEnum::ALL,
                        'update' => PermissionTypeEnum::OWN,
                        'delete' => PermissionTypeEnum::OWN,
                        'import' => PermissionTypeEnum::ALL,
                        'export' => PermissionTypeEnum::ALL,
                    ]
                );
            }

            $role = Role::updateOrCreate([
                'title' => DefaultRolesEnum::CLIENT->label(),
            ], [
                'original_title' => DefaultRolesEnum::CLIENT->value,
            ]);
        });
    }
}
