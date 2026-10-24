<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private array $userData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userData = [
            'email' => 'example242@test.com',
            'password' => '242_test',
        ];

        $this->user = User::factory()->create([
            ...$this->userData,
            'password' => Hash::make($this->userData['password']),
        ]);

    }

    public function test_login(): void
    {
        $response = $this->postJson('/api/login', $this->userData);

        $response->assertStatus(200);
    }

    public function test_authenticated_user(): void
    {
        Auth::login($this->user);

        $response = $this->get('/api/me');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user(): void
    {
        $response = $this->get('/api/me');

        $response->assertStatus(401);
    }
}
