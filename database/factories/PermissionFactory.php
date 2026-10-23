<?php

namespace Database\Factories;

use App\Enums\PermissionTypeEnum;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity' => fake()->title(),
            'role_id' => Role::COMMON,
            'view' => PermissionTypeEnum::ALL->value,
            'create' => PermissionTypeEnum::ALL->value,
            'update' => PermissionTypeEnum::ALL->value,
            'delete' => PermissionTypeEnum::ALL->value,
        ];
    }
}
