<?php

namespace Tests\Feature;

use App\Models\Field;
use App\Models\SalesFunnel;
use App\Models\Section;
use App\Models\Role;
use App\Models\User;
use App\Models\UserField;
use App\Services\SalesFunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFunnelSectionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_sync_adds_distinct_positions_to_each_users_template(): void
    {
        $users = collect([1, 2])->map(fn ($number): User => User::withoutEvents(fn (): User => User::create([
            'name' => 'Иван',
            'surname' => 'Иванов',
            'email' => 'funnel-'.$number.'@example.test',
            'password' => 'unused',
        ])));
        $funnel = SalesFunnel::firstOrFail();
        $field = Field::create(['entity' => 'deals', 'name' => 'Название', 'type' => 'string', 'code' => 'title']);
        $userField = UserField::create(['entity' => 'deals', 'name' => 'Дополнение', 'type' => 'string', 'code' => 'addition']);

        app(SalesFunnelService::class)->updateSalesFunnel($funnel, [
            'fields' => [
                ['field_id' => $field->id],
                ['user_field_id' => $userField->id],
            ],
        ]);

        foreach ($users as $user) {
            $section = Section::where('user_id', $user->id)->where('sales_funnel_id', $funnel->id)->firstOrFail();
            $this->assertCount(2, $section->fields);
            $this->assertSame([1, 2], $section->fields->pluck('position')->sort()->values()->all());
        }
    }

    public function test_empty_extra_funnel_can_be_deleted_by_administrator(): void
    {
        $role = Role::create(['title' => 'Руководитель']);
        $admin = User::withoutEvents(fn (): User => User::create([
            'name' => 'Admin', 'surname' => 'User', 'email' => 'funnel-admin@example.test',
            'password' => 'unused', 'role_id' => $role->id,
        ]));
        $funnel = SalesFunnel::create(['title' => 'Extra', 'position' => 99]);

        app(SalesFunnelService::class)->deleteSalesFunnel($funnel, $admin);

        $this->assertDatabaseMissing('sales_funnels', ['id' => $funnel->id]);
    }
}
