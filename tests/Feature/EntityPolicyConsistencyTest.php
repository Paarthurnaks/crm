<?php

namespace Tests\Feature;

use App\Enums\PermissionTypeEnum;
use App\Models\Deal;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class EntityPolicyConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_deal_policy_and_user_method_agree_without_http_actor(): void
    {
        $role = Role::create(['title' => 'seller']);
        Permission::create([
            'role_id' => $role->id, 'entity' => 'deals',
            'view' => PermissionTypeEnum::OWN->value,
            'create' => PermissionTypeEnum::FORBIDDEN->value,
            'update' => PermissionTypeEnum::FORBIDDEN->value,
            'delete' => PermissionTypeEnum::FORBIDDEN->value,
        ]);
        $creator = $this->user($role, 'creator@example.test');
        $other = $this->user($role, 'other@example.test');
        $funnel = SalesFunnel::firstOrFail();
        $stage = SalesStage::where('sales_funnel_id', $funnel->id)->firstOrFail();
        $deal = Deal::create([
            'title' => 'Policy', 'sales_funnel_id' => $funnel->id,
            'sales_stage_id' => $stage->id, 'creator_id' => $creator->id,
            'responsible_id' => $creator->id, 'note' => '',
        ]);

        $this->assertSame(PermissionTypeEnum::OWN->value, $creator->permissionScope('deals', 'view'));
        $this->assertSame($creator->id, (int) $deal->creator_id);
        $this->assertTrue(Gate::forUser($creator)->allows('view', $deal));
        $this->assertTrue($creator->hasDealAccess($deal->id));
        $this->assertFalse(Gate::forUser($other)->allows('view', $deal));
        $this->assertFalse($other->hasDealAccess($deal->id));
    }

    public function test_report_policy_uses_report_access_rule(): void
    {
        $author = $this->user(null, 'report-author@example.test');
        $other = $this->user(null, 'report-other@example.test');
        $report = Report::create([
            'title' => 'Private', 'author_id' => $author->id,
            'period_from' => '2024-01-01 00:00:00', 'period_to' => '2024-12-31 23:59:59',
            'filter' => [],
        ]);
        $report->userAccess()->attach($author->id);

        $this->assertTrue(Gate::forUser($author)->allows('view', $report));
        $this->assertFalse(Gate::forUser($other)->allows('view', $report));
    }

    private function user(?Role $role, string $email): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User', 'email' => $email,
            'password' => 'password', 'role_id' => $role?->id,
        ]));
    }
}
