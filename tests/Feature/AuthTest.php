<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
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

    public function test_avatar_change_rejects_non_file_input(): void
    {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/change-user-avatar', ['avatar' => 'not-an-image'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_password_change_consumes_reset_code(): void
    {
        $key = 'reset_password_code_'.$this->user->email;
        Redis::shouldReceive('get')->once()->with($key)->andReturn('valid-code');
        Redis::shouldReceive('del')->once()->with($key)->andReturn(1);

        app(AuthService::class)->changePassword([
            'email' => $this->user->email,
            'code' => 'valid-code',
            'password' => 'new-password',
        ]);

        $this->assertTrue(Hash::check('new-password', $this->user->fresh()->password));
    }
}
