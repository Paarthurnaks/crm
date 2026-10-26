<?php

namespace Tests\Feature;

use App\Enums\DealEventTypeEnum;
use App\Models\DealEvent;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\User;
use App\Services\ChatService;
use App\Services\DealService;
use App\Services\EntityActivityService;
use App\Services\UserFieldService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ExplicitDomainWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deal_update_records_each_changed_field_and_stage_without_an_http_actor(): void
    {
        Notification::fake();
        $actor = $this->createBareUser('deal-workflow@example.test');
        $funnel = SalesFunnel::firstOrFail();
        $initialStage = SalesStage::where('sales_funnel_id', $funnel->id)->firstOrFail();
        $nextStage = SalesStage::create([
            'title' => 'Следующий этап',
            'color' => '#000000',
            'position' => 2,
            'type' => $initialStage->type,
            'sales_funnel_id' => $funnel->id,
        ]);

        $fields = Mockery::mock(UserFieldService::class);
        $fields->shouldReceive('setupBooleanValues')->once();
        $fields->shouldReceive('updateUserFieldEntity')->once()->andReturnUsing(function ($deal, $data): void {
            $deal->title = $data['title'];
            $deal->amount = $data['amount'];
            $deal->sales_stage_id = $data['sales_stage_id'];
        });

        $service = new DealService(Mockery::mock(EntityActivityService::class), $fields);
        $deal = $service->createDeal(['title' => 'Исходная сделка'], $actor);
        $service->updateDeal($deal, [
            'title' => 'Новая сделка',
            'amount' => 100,
            'sales_stage_id' => $nextStage->id,
        ], $actor);

        $this->assertSame('Новая сделка', $deal->fresh()->title);
        $this->assertDatabaseHas('deal_events', ['deal_id' => $deal->id, 'type' => DealEventTypeEnum::CHANGE_STAGE->value]);
        $this->assertSame(2, DealEvent::where('deal_id', $deal->id)
            ->where('type', DealEventTypeEnum::CHANGE_FIELD->value)->count());
    }

    public function test_user_provisioning_rolls_back_when_required_chat_setup_fails(): void
    {
        $chats = Mockery::mock(ChatService::class);
        $chats->shouldReceive('setupChats')->once()->andThrow(new RuntimeException('chat setup failed'));
        $service = new UserService($chats);

        try {
            $service->createProvisionedUser([
                'name' => 'Иван',
                'surname' => 'Иванов',
                'email' => 'rollback@example.test',
                'password' => 'unused',
            ]);
            $this->fail('Ожидался откат при отказе инициализации чатов.');
        } catch (RuntimeException) {
            $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
            $this->assertSame(0, DB::table('sections')->whereNotNull('user_id')->count());
        }
    }

    public function test_deal_creation_rolls_back_event_when_initialization_fails(): void
    {
        Notification::fake();
        $actor = $this->createBareUser('deal-rollback@example.test');
        $fields = Mockery::mock(UserFieldService::class);
        $fields->shouldReceive('setupBooleanValues')->once()->andThrow(new RuntimeException('field initialization failed'));
        $service = new DealService(Mockery::mock(EntityActivityService::class), $fields);
        $eventCount = DealEvent::count();

        try {
            $service->createDeal(['title' => 'Откатываемая сделка'], $actor);
            $this->fail('Ожидался откат при отказе инициализации полей.');
        } catch (RuntimeException) {
            $this->assertDatabaseMissing('deals', ['title' => 'Откатываемая сделка']);
            $this->assertSame($eventCount, DealEvent::count());
        }
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
