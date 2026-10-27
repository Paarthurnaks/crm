<?php

namespace Tests\Unit;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealClientValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_accessor_preserves_legacy_text_and_resolves_numeric_user(): void
    {
        $client = User::withoutEvents(fn (): User => User::create([
            'name' => 'Клиент',
            'surname' => 'Тестовый',
            'email' => 'deal-client@example.test',
            'password' => 'unused',
        ]));
        $deal = new Deal;

        $deal->setRawAttributes(['client' => 'str_Компания']);
        $this->assertSame('Компания', $deal->client);

        $deal->setRawAttributes(['client' => (string) $client->id]);
        $this->assertSame($client->id, $deal->client->id);

        $deal->setRelation('resolvedClient', $client);
        $this->assertSame($client, $deal->client);
    }

    public function test_own_deals_scope_uses_explicit_actor_id(): void
    {
        $this->assertSame([42, 42], Deal::ownDeals(42)->getBindings());
    }
}
