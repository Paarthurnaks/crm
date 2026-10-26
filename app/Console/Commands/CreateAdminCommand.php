<?php

namespace App\Console\Commands;

use App\Enums\DefaultRolesEnum;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'create:admin {name} {surname} {email} {password} {--super : Create a super admin}';

    protected $description = 'Create a new admin user';

    public function handle(UserService $userService): int
    {
        $name = $this->argument('name');
        $surname = $this->argument('surname');
        $email = $this->argument('email');
        $password = $this->argument('password');
        $isSuperAdmin = $this->option('super');

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error('Пользователь с таким email уже существует!');

            return 1;
        }

        $roleId = Role::where('original_title', DefaultRolesEnum::SUPER_ADMIN->value)->value('id');

        if (! $roleId) {
            $this->error('Роль супер-админа не инициализирована в базе данных!');

            return 1;
        }

        // Create the user
        $user = $userService->createProvisionedUser([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $roleId,
        ]);

        if ($isSuperAdmin) {
            $user->highLevelAdmin()->create(['user_id' => $user->id]);
        }

        $this->info('Админ успешно создан!');

        return 0;
    }
}
