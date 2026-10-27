<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationSearchPortabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_deals_relation_uses_string_key_without_vendor_specific_regex(): void
    {
        $user = new User;
        $user->id = 42;

        $relation = $user->partnerDeals();

        $this->assertSame('42', $relation->getParentKey());
        $this->assertStringNotContainsString('REGEXP', $relation->toSql());
    }

    public function test_notification_search_ignores_html_tags_on_supported_drivers(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('Требуется MySQL или PostgreSQL');
        }

        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]));
        $this->actingAs($user);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'Example',
            'data' => ['html' => '<b>visible</b> message'],
        ]);
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'Example',
            'data' => ['html' => '<b>other</b> message'],
        ]);

        $result = (new NotificationService)->getNotifications(['search_term' => 'visible message'], $user);

        $this->assertSame(1, $result['notifications']['meta']['total']);
    }
}
