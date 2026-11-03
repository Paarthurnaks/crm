<?php

namespace Tests\Feature;

use App\Enums\PermissionTypeEnum;
use App\Models\Deal;
use App\Models\DealEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DealApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $role = Role::create(['title' => 'Deal manager']);
        Permission::create([
            'role_id' => $role->id,
            'entity' => 'deals',
            'view' => PermissionTypeEnum::ALL->value,
            'create' => PermissionTypeEnum::ALL->value,
            'update' => PermissionTypeEnum::ALL->value,
            'delete' => PermissionTypeEnum::ALL->value,
        ]);
        $actor = User::withoutEvents(fn (): User => User::create([
            'name' => 'Deal', 'surname' => 'Manager', 'email' => 'deal-manager@example.test',
            'password' => 'password', 'role_id' => $role->id,
        ]));
        $this->actingAs($actor, 'api');
    }

    public function test_deal_crud_exposes_resource_and_persists_stage_client_and_events(): void
    {
        $funnel = SalesFunnel::firstOrFail();
        $firstStage = SalesStage::where('sales_funnel_id', $funnel->id)->firstOrFail();
        $nextStage = SalesStage::create([
            'title' => 'Next stage', 'color' => '#333333', 'position' => 2,
            'type' => $firstStage->type, 'sales_funnel_id' => $funnel->id,
        ]);

        $created = $this->postJson('/api/deals', [
            'title' => 'API contract deal', 'sales_funnel_id' => $funnel->id,
            'amount' => 1250, 'client_id' => 'External client',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'API contract deal')
            ->assertJsonPath('data.sales_funnel.id', $funnel->id)
            ->assertJsonPath('data.sales_stage.id', $firstStage->id)
            ->assertJsonStructure(['data' => ['id', 'creator', 'responsible', 'user_field_values']]);

        $dealId = $created->json('data.id');
        $this->assertDatabaseHas('deals', ['id' => $dealId, 'client' => 'str_External client']);
        $this->assertSame(1, DealEvent::where('deal_id', $dealId)->count());

        $this->getJson('/api/deals/'.$dealId)->assertOk()->assertJsonPath('data.id', $dealId);
        $this->getJson('/api/deals?search=API%20contract')->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $dealId);
        $this->getJson('/api/deals?search=absent')->assertOk()->assertJsonPath('data.total', 0);

        $this->putJson('/api/deals/'.$dealId, [
            'title' => 'Changed deal', 'sales_stage_id' => $nextStage->id,
            'client_id' => 'New client',
        ])->assertOk()->assertJsonPath('data.title', 'Changed deal');
        $this->assertDatabaseHas('deals', [
            'id' => $dealId, 'sales_stage_id' => $nextStage->id, 'client' => 'str_New client',
        ]);
        $this->assertGreaterThan(1, DealEvent::where('deal_id', $dealId)->count());

        $this->deleteJson('/api/deals/'.$dealId)->assertOk();
        $this->assertDatabaseMissing('deals', ['id' => $dealId]);
    }

    public function test_invalid_deal_request_does_not_create_or_change_a_deal(): void
    {
        $this->postJson('/api/deals', ['creator_id' => 123, 'sales_funnel_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors(['creator_id', 'sales_funnel_id']);
        $this->assertSame(0, Deal::count());

        $deal = $this->postJson('/api/deals', ['title' => 'Original'])->assertCreated()->json('data.id');
        $this->putJson('/api/deals/'.$deal, ['title' => 'Changed', 'sales_stage_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('sales_stage_id');
        $this->assertDatabaseHas('deals', ['id' => $deal, 'title' => 'Original']);
    }

    public function test_deal_comments_activity_and_documents_are_available_to_authorized_user(): void
    {
        $dealId = $this->postJson('/api/deals', ['title' => 'Timeline deal'])
            ->assertCreated()->json('data.id');
        $commentId = $this->postJson('/api/deals/'.$dealId.'/comments', [
            'comment' => 'Customer contacted',
        ])->assertOk()->assertJsonPath('data.comment', 'Customer contacted')->json('data.id');

        $this->getJson('/api/deals/'.$dealId.'/comments')->assertOk()
            ->assertJsonPath('data.0.id', $commentId);
        $this->getJson('/api/deals/'.$dealId.'/activity')->assertOk();
        $this->getJson('/api/deals/'.$dealId.'/documents')->assertOk();
        $this->postJson('/api/deals/'.$dealId.'/comments', [])
            ->assertUnprocessable()->assertJsonValidationErrors('comment');
        $this->getJson('/api/deals/999999/comments')->assertNotFound();
    }
}
