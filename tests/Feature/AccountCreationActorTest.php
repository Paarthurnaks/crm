<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountCreationActorTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_author_comes_from_explicit_actor(): void
    {
        $actor = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test', 'surname' => 'User',
            'email' => 'account-actor@example.test', 'password' => 'unused',
        ]));
        $funnel = SalesFunnel::firstOrFail();
        $stage = SalesStage::where('sales_funnel_id', $funnel->id)->firstOrFail();
        $deal = Deal::create([
            'title' => 'Account deal',
            'sales_funnel_id' => $funnel->id,
            'sales_stage_id' => $stage->id,
            'creator_id' => $actor->id,
            'responsible_id' => $actor->id,
            'note' => '',
        ]);

        $account = app(AccountService::class)->create(['deal_id' => $deal->id, 'sum' => 100], $actor);

        $this->assertSame($actor->id, $account->author_id);
    }
}
