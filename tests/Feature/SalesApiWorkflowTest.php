<?php

namespace Tests\Feature;

use App\Enums\PermissionTypeEnum;
use App\Models\Deal;
use App\Models\Field;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\SalesTunnel;
use App\Models\Section;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['title' => 'Руководитель']);
        Permission::create([
            'role_id' => $role->id, 'entity' => 'deals',
            'view' => PermissionTypeEnum::ALL->value,
            'create' => PermissionTypeEnum::ALL->value,
            'update' => PermissionTypeEnum::ALL->value,
            'delete' => PermissionTypeEnum::ALL->value,
        ]);
        $this->actor = User::withoutEvents(fn (): User => User::create([
            'name' => 'Sales', 'surname' => 'Admin', 'email' => 'sales-admin@example.test',
            'password' => 'password', 'role_id' => $role->id,
        ]));
        $this->actingAs($this->actor, 'api');
    }

    public function test_funnel_crud_preserves_stage_order_and_rejects_deletion_when_used(): void
    {
        $created = $this->postJson('/api/sales-funnels', [
            'title' => 'Second funnel', 'position' => 2,
            'stages' => [
                ['title' => 'Incoming', 'type' => 'default', 'position' => 1],
                ['title' => 'Won', 'type' => 'success', 'position' => 2],
                ['title' => 'Lost', 'type' => 'unsuccess', 'position' => 3],
            ],
        ])->assertCreated()->assertJsonPath('data.title', 'Second funnel');
        $funnelId = $created->json('data.id');
        $this->assertSame([1, 2, 3], SalesStage::where('sales_funnel_id', $funnelId)
            ->orderBy('position')->pluck('position')->all());

        $this->getJson('/api/sales-funnels/'.$funnelId)->assertOk()->assertJsonPath('data.id', $funnelId);
        $this->getJson('/api/sales-funnels')->assertOk()->assertJsonPath('data.total', 2);
        $this->putJson('/api/sales-funnels/'.$funnelId, ['title' => 'Updated funnel', 'position' => 3])
            ->assertOk()->assertJsonPath('data.title', 'Updated funnel');

        $stage = SalesStage::where('sales_funnel_id', $funnelId)->firstOrFail();
        Deal::create([
            'title' => 'Uses funnel', 'sales_funnel_id' => $funnelId,
            'sales_stage_id' => $stage->id, 'creator_id' => $this->actor->id,
            'responsible_id' => $this->actor->id, 'note' => '',
        ]);
        $this->deleteJson('/api/sales-funnels/'.$funnelId)->assertForbidden();
        $this->assertDatabaseHas('sales_funnels', ['id' => $funnelId]);
    }

    public function test_stage_and_tunnel_endpoints_validate_links_and_block_cycles(): void
    {
        $firstFunnel = SalesFunnel::firstOrFail();
        $secondFunnel = SalesFunnel::create(['title' => 'Other', 'position' => 2]);
        $firstStage = SalesStage::where('sales_funnel_id', $firstFunnel->id)->firstOrFail();

        $this->postJson('/api/sales-stages', [
            'sales_funnel_id' => 999999, 'title' => 'Invalid', 'type' => 'default',
            'position' => 1, 'color' => '#333333',
        ])->assertUnprocessable()->assertJsonValidationErrors('sales_funnel_id');

        $created = $this->postJson('/api/sales-stages', [
            'sales_funnel_id' => $secondFunnel->id, 'title' => 'Target',
            'type' => 'default', 'position' => 1, 'color' => '#333333',
        ])->assertOk()->assertJsonPath('data.title', 'Target');
        $targetId = $created->json('data.id');
        $this->getJson('/api/sales-stages/'.$targetId)->assertOk()->assertJsonPath('data.id', $targetId);
        $this->putJson('/api/sales-stages/'.$targetId, ['title' => 'Renamed', 'position' => 2])
            ->assertOk()->assertJsonPath('data.title', 'Renamed');

        $this->postJson('/api/sales-tunnels', [
            'from_stage_id' => 999999, 'to_stage_id' => $targetId,
        ])->assertUnprocessable()->assertJsonValidationErrors('from_stage_id');
        $tunnel = $this->postJson('/api/sales-tunnels', [
            'from_stage_id' => $firstStage->id, 'to_stage_id' => $targetId,
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('sales_tunnels', ['id' => $tunnel]);
        $this->postJson('/api/sales-tunnels', [
            'from_stage_id' => $targetId, 'to_stage_id' => $firstStage->id,
        ])->assertForbidden();
        $this->assertSame(1, SalesTunnel::count());

        $this->deleteJson('/api/sales-tunnels/'.$tunnel)->assertOk();
        $this->deleteJson('/api/sales-stages/'.$targetId)->assertOk();
        $this->assertDatabaseMissing('sales_stages', ['id' => $targetId]);
    }

    public function test_funnel_update_syncs_template_fields_and_rejects_incomplete_stages(): void
    {
        $funnel = SalesFunnel::firstOrFail();
        $field = Field::create([
            'entity' => 'deals', 'name' => 'Title', 'type' => 'string', 'code' => 'title',
        ]);
        $userField = UserField::create([
            'entity' => 'deals', 'name' => 'Extra', 'type' => 'string', 'code' => 'extra',
        ]);

        $this->putJson('/api/sales-funnels/'.$funnel->id, [
            'fields' => [['field_id' => $field->id], ['user_field_id' => $userField->id]],
        ])->assertOk();

        $section = Section::where('user_id', $this->actor->id)
            ->where('sales_funnel_id', $funnel->id)->firstOrFail();
        $this->assertSame([1, 2], $section->fields()->orderBy('position')->pluck('position')->all());

        $this->putJson('/api/sales-funnels/'.$funnel->id, [
            'stages' => [['title' => 'Only default', 'type' => 'default', 'position' => 1]],
        ])->assertStatus(400);
        $this->assertSame(1, $funnel->salesStages()->count());
    }
}
