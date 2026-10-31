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

    public function test_date_filter_keeps_unread_total_independent_of_page(): void
    {
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User',
            'email' => fake()->unique()->safeEmail(), 'password' => 'unused',
        ]));
        $this->actingAs($user);
        foreach (['2024-01-01 12:00:00', '2024-01-02 12:00:00'] as $date) {
            $notification = $user->notifications()->create([
                'id' => (string) Str::uuid(), 'type' => 'Example',
                'data' => ['html' => 'message'],
            ]);
            $notification->forceFill(['created_at' => $date])->save();
        }

        $result = (new NotificationService)->getNotifications(['date' => '2024-01-01'], $user);

        $this->assertSame(1, $result['notifications']['meta']['total']);
        $this->assertSame(2, $result['total_unread_notifications']);
    }

    public function test_unsupported_driver_rejects_notification_search(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('Требуется неподдерживаемый драйвер');
        }

        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User',
            'email' => fake()->unique()->safeEmail(), 'password' => 'unused',
        ]));
        $this->expectException(\RuntimeException::class);

        (new NotificationService)->getNotifications(['search_term' => 'message'], $user);
    }
}
