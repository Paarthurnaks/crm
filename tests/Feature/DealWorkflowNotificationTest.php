<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\DealEventNotification;
use App\Services\DealService;
use App\Services\EntityActivityService;
use App\Services\UserFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class DealWorkflowNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deal_event_notifies_responsible_after_successful_creation(): void
    {
        Notification::fake();
        $creator = $this->createBareUser('creator@example.test');
        $responsible = $this->createBareUser('responsible@example.test');

        $fields = Mockery::mock(UserFieldService::class);
        $fields->shouldReceive('setupBooleanValues')->once();
        $service = new DealService(Mockery::mock(EntityActivityService::class), $fields);

        $service->createDeal([
            'title' => 'Новая сделка',
            'responsible_id' => $responsible->id,
        ], $creator);

        Notification::assertSentTo($responsible, DealEventNotification::class);
        Notification::assertNotSentTo($creator, DealEventNotification::class);
    }

    private function createBareUser(string $email): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Иван',
            'surname' => 'Иванов',
            'email' => $email,
            'password' => 'unused',
        ]));
    }
}
