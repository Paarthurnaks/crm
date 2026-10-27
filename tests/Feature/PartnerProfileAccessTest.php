<?php

namespace Tests\Feature;

use App\Enums\DefaultRolesEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerProfileAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_can_change_own_color(): void
    {
        $role = Role::create(['title' => 'Partner', 'original_title' => DefaultRolesEnum::PARTNER->value]);
        $partner = $this->createUser($role);

        $this->actingAs($partner, 'api')
            ->postJson('/api/partner-color', ['color' => '#123456'])
            ->assertOk();

        $this->assertSame('#123456', $partner->fresh()->color);
    }

    public function test_other_role_cannot_change_partner_color(): void
    {
        $role = Role::create(['title' => 'Staff']);
        $staff = $this->createUser($role);

        $this->actingAs($staff, 'api')
            ->postJson('/api/partner-color', ['color' => '#123456'])
            ->assertForbidden();
    }

    private function createUser(Role $role): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'unused', 'role_id' => $role->id,
        ]));
    }
}
