<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->make([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        app(UserService::class)->createProvisionedUser($user->getAttributes());
    }
}
