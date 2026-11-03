<?php

namespace Tests\Feature;

use App\Mail\SendLinkChangePasswordMail;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\JWT;
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

    public function test_jwt_from_login_authenticates_api_request(): void
    {
        $token = $this->postJson('/api/login', $this->userData)
            ->assertOk()
            ->json('data.token');

        $this->assertIsString($token);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_login_rejects_wrong_password_without_changing_user_state(): void
    {
        $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertFalse((bool) $this->user->fresh()->active);
    }

    public function test_login_validates_required_credentials(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_refresh_invalidates_previous_token_and_returns_a_working_token(): void
    {
        $oldToken = $this->postJson('/api/login', $this->userData)->json('data.token');
        $this->assertIsString($oldToken);

        $newToken = $this->withToken($oldToken)
            ->postJson('/api/refresh')
            ->assertOk()
            ->json('data.token');
        $this->assertIsString($newToken);
        $this->assertNotSame($oldToken, $newToken);

        Auth::forgetGuards();
        $this->withToken($oldToken)->getJson('/api/me')->assertUnauthorized();
        app(JWT::class)->unsetToken();
        Auth::forgetGuards();
        $this->withToken($newToken)->getJson('/api/me')->assertOk();
    }

    public function test_logout_invalidates_token_and_marks_user_inactive(): void
    {
        $token = $this->postJson('/api/login', $this->userData)->json('data.token');
        $this->assertIsString($token);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertFalse((bool) $this->user->fresh()->active);
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_password_reset_link_is_sent_for_existing_user(): void
    {
        Mail::fake();
        Redis::shouldReceive('setex')->once();

        $this->postJson('/api/change-password/send-link', ['email' => $this->user->email])
            ->assertOk();

        Mail::assertSent(SendLinkChangePasswordMail::class);
    }

    public function test_password_reset_request_rejects_unknown_email(): void
    {
        $this->postJson('/api/change-password/send-link', ['email' => 'missing@example.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_password_change_rejects_wrong_code_without_changing_password(): void
    {
        Redis::shouldReceive('get')->once()->andReturn('expected-code');

        $this->postJson('/api/change-password/change', [
            'email' => $this->user->email,
            'code' => 'wrong-code',
            'password' => 'new-password',
        ])->assertBadRequest();

        $this->assertTrue(Hash::check($this->userData['password'], $this->user->fresh()->password));
    }

    public function test_password_change_rejects_expired_code_without_changing_password(): void
    {
        Redis::shouldReceive('get')->once()->andReturn(null);

        $this->postJson('/api/change-password/change', [
            'email' => $this->user->email,
            'code' => 'expired-code',
            'password' => 'new-password',
        ])->assertBadRequest();

        $this->assertTrue(Hash::check($this->userData['password'], $this->user->fresh()->password));
    }

    public function test_password_can_be_changed_through_api_with_valid_code(): void
    {
        Redis::shouldReceive('get')->once()->andReturn('valid-code');
        Redis::shouldReceive('del')->once()->andReturn(1);

        $this->postJson('/api/change-password/change', [
            'email' => $this->user->email,
            'code' => 'valid-code',
            'password' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $this->user->fresh()->password));
    }

    public function test_authenticated_user_can_change_avatar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user, 'api')
            ->post('/api/change-user-avatar', [
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertOk();

        $media = $this->user->fresh()->getFirstMedia();
        $this->assertNotNull($media);
        $this->assertSame('avatar.jpg', $media->file_name);
        $this->assertTrue(Storage::disk('public')->exists($media->getPathRelativeToRoot()));
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
