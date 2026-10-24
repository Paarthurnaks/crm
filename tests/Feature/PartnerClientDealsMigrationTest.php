<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PartnerClientDealsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_installation_has_columns_required_by_partner_deal_service(): void
    {
        $this->assertTrue(Schema::hasTable('partner_client_deals'));
        $this->assertTrue(Schema::hasColumns('partner_client_deals', [
            'id', 'deal_id', 'client_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_rollback_keeps_existing_partner_client_links(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'partner-migration@example.test',
            'password' => 'unused',
        ]);
        $funnelId = DB::table('sales_funnels')->insertGetId(['title' => 'Test']);
        $stageId = DB::table('sales_stages')->insertGetId([
            'title' => 'Test',
            'color' => '#000000',
            'position' => 1,
            'type' => 'active',
            'sales_funnel_id' => $funnelId,
        ]);
        $dealId = DB::table('deals')->insertGetId([
            'title' => 'Test',
            'sales_stage_id' => $stageId,
            'sales_funnel_id' => $funnelId,
            'creator_id' => $userId,
            'responsible_id' => $userId,
            'note' => '',
        ]);
        DB::table('partner_client_deals')->insert([
            'deal_id' => $dealId,
            'client_id' => $userId,
        ]);

        $migration = require database_path('migrations/2026_10_24_000000_restore_partner_client_deals_table.php');

        try {
            $migration->down();
            $this->fail('Откат не должен удалять связи сделок с клиентами.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Нельзя откатить восстановление partner_client_deals, пока в таблице есть записи.',
                $exception->getMessage()
            );
        }

        $this->assertTrue(Schema::hasTable('partner_client_deals'));
        $this->assertSame(1, DB::table('partner_client_deals')->count());
    }
}
