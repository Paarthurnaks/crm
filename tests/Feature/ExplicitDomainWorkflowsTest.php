<?php

namespace Tests\Feature;

use App\Enums\DealEventTypeEnum;
use App\Models\DealEvent;
use App\Models\Field;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use App\Models\UserField;
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

    public function test_user_provisioning_gives_funnel_fields_distinct_positions(): void
    {
        $funnel = SalesFunnel::firstOrFail();
        $field = Field::create(['name' => 'Title', 'entity' => 'deals', 'type' => 'string', 'code' => 'title']);
        $userField = UserField::create(['name' => 'Custom', 'entity' => 'deals', 'type' => 'string', 'code' => 'custom']);
        $funnel->fields()->attach($field->id);
        $funnel->userFields()->attach($userField->id);
        $chats = Mockery::mock(ChatService::class);
        $chats->shouldReceive('setupChats')->once();

        $user = (new UserService($chats))->createProvisionedUser([
            'name' => 'Иван', 'surname' => 'Иванов',
            'email' => 'positions@example.test', 'password' => 'unused',
        ]);

        $section = Section::where('user_id', $user->id)->where('sales_funnel_id', $funnel->id)->firstOrFail();
        $this->assertSame([1, 2], $section->fields->pluck('position')->sort()->values()->all());
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

    public function test_deal_creation_copies_template_section_and_fields_without_changing_template(): void
    {
        $actor = $this->createBareUser('deal-template@example.test');
        $funnel = SalesFunnel::firstOrFail();
        $field = Field::create(['name' => 'Title', 'entity' => 'deals', 'type' => 'string', 'code' => 'title']);
        $template = Section::create([
            'user_id' => $actor->id,
            'entity' => 'deals',
            'title' => 'Template',
            'position' => 1,
            'sales_funnel_id' => $funnel->id,
            'is_template' => true,
        ]);
        SectionField::create(['section_id' => $template->id, 'field_id' => $field->id, 'position' => 1]);
        $fields = Mockery::mock(UserFieldService::class);
        $fields->shouldReceive('setupBooleanValues')->once();

        $deal = (new DealService(Mockery::mock(EntityActivityService::class), $fields))
            ->createDeal(['title' => 'Сделка', 'sales_funnel_id' => $funnel->id], $actor);

        $copy = Section::where('deal_id', $deal->id)->where('title', 'Template')->firstOrFail();
        $this->assertNotSame($template->id, $copy->id);
        $this->assertFalse((bool) $copy->is_template);
        $this->assertSame([$field->id], $copy->fields->pluck('field_id')->all());
        $this->assertTrue((bool) $template->fresh()->is_template);
    }

    public function test_client_change_event_preserves_null_previous_value(): void
    {
        $actor = $this->createBareUser('deal-client-change@example.test');
        $fields = Mockery::mock(UserFieldService::class);
        $fields->shouldReceive('setupBooleanValues')->once();
        $fields->shouldReceive('updateUserFieldEntity')->once()->andReturnUsing(function ($deal): void {
            $deal->client = 'Компания';
        });
        $service = new DealService(Mockery::mock(EntityActivityService::class), $fields);
        $deal = $service->createDeal(['title' => 'Без клиента'], $actor);

        $service->updateDeal($deal, ['client' => 'Компания'], $actor);

        $this->assertDatabaseHas('deal_events', [
            'deal_id' => $deal->id,
            'type' => DealEventTypeEnum::CHANGE_CLIENT->value,
            'old_value' => null,
            'new_value' => 'Компания',
        ]);
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
