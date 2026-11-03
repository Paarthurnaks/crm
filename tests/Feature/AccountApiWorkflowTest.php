<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Deal;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_crud_filters_by_deal_and_uses_authenticated_author(): void
    {
        $actor = $this->actor();
        $deal = $this->deal($actor);
        $this->actingAs($actor, 'api');

        $created = $this->postJson('/api/accounts', [
            'deal_id' => $deal->id, 'sum' => 250, 'invoice_date' => '2026-01-10',
        ])->assertCreated()
            ->assertJsonPath('data.deal.id', $deal->id)
            ->assertJsonPath('data.author.id', $actor->id)
            ->assertJsonPath('data.invoice_date', '2026-01-10');
        $accountId = $created->json('data.id');
        $this->assertDatabaseHas('accounts', ['id' => $accountId, 'author_id' => $actor->id, 'sum' => 250]);

        $this->getJson('/api/accounts/'.$accountId)->assertOk()->assertJsonPath('data.id', $accountId);
        $this->getJson('/api/accounts?deal_id='.$deal->id)->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $accountId);

        $this->putJson('/api/accounts/'.$accountId, ['sum' => 375])
            ->assertOk()->assertJsonPath('data.sum', 375);
        $this->assertDatabaseHas('accounts', ['id' => $accountId, 'sum' => 375]);

        $this->deleteJson('/api/accounts/'.$accountId)->assertOk();
        $this->assertDatabaseMissing('accounts', ['id' => $accountId]);
        $this->getJson('/api/accounts/'.$accountId)->assertNotFound();
    }

    public function test_account_validation_rejects_bad_deal_and_sum_without_mutation(): void
    {
        $actor = $this->actor();
        $deal = $this->deal($actor);
        $this->actingAs($actor, 'api');

        $this->postJson('/api/accounts', ['deal_id' => 999999, 'sum' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['deal_id', 'sum']);
        $this->assertSame(0, Account::count());

        $id = $this->postJson('/api/accounts', ['deal_id' => $deal->id, 'sum' => 100])
            ->assertCreated()->json('data.id');
        $this->putJson('/api/accounts/'.$id, ['sum' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('sum');
        $this->assertDatabaseHas('accounts', ['id' => $id, 'sum' => 100]);
    }

    private function actor(): User
    {
        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Account', 'surname' => 'Tester',
            'email' => 'account-tester@example.test', 'password' => 'password',
        ]));
    }

    private function deal(User $actor): Deal
    {
        $funnel = SalesFunnel::firstOrFail();
        $stage = SalesStage::where('sales_funnel_id', $funnel->id)->firstOrFail();

        return Deal::create([
            'title' => 'Account deal', 'sales_funnel_id' => $funnel->id,
            'sales_stage_id' => $stage->id, 'creator_id' => $actor->id,
            'responsible_id' => $actor->id, 'note' => '',
        ]);
    }
}
