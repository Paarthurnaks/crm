<?php

namespace Tests\Feature;

use App\Enums\PermissionTypeEnum;
use App\Http\Filters\DealFilter;
use App\Models\Deal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DealListClientPreloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_resolves_clients_without_queries_during_serialization(): void
    {
        $role = Role::create(['title' => 'deals-viewer']);
        Permission::create([
            'role_id' => $role->id,
            'entity' => 'deals',
            'view' => PermissionTypeEnum::ALL->value,
            'create' => PermissionTypeEnum::FORBIDDEN->value,
            'update' => PermissionTypeEnum::FORBIDDEN->value,
            'delete' => PermissionTypeEnum::FORBIDDEN->value,
        ]);
        $actor = $this->createUser('deal-viewer@example.test', $role);
        $clients = [
            $this->createUser('client-one@example.test'),
            $this->createUser('client-two@example.test'),
        ];
        $funnel = SalesFunnel::firstOrFail();
        $stage = SalesStage::where('sales_funnel_id', $funnel->id)->firstOrFail();

        foreach ($clients as $index => $client) {
            Deal::withoutEvents(fn () => Deal::create([
                'title' => 'Сделка '.$index,
                'sales_funnel_id' => $funnel->id,
                'sales_stage_id' => $stage->id,
                'creator_id' => $actor->id,
                'responsible_id' => $actor->id,
                'note' => '',
                'client' => $client->id,
            ]));
        }

        $deals = app(DealService::class)->getDeals(new DealFilter(new Request), [], $actor);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $clientIds = $deals->getCollection()->map(fn (Deal $deal) => $deal->client?->id)->all();

        $expectedIds = array_map(fn (User $client) => $client->id, $clients);
        $this->assertEqualsCanonicalizing($expectedIds, $clientIds);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    private function createUser(string $email, ?Role $role = null): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => $email,
            'password' => 'unused',
            'role_id' => $role?->id,
        ]));
    }
}
