<?php

namespace Tests\Feature;

use App\Services\SalesTunnelService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTunnelCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_stage_is_rejected_before_creating_tunnel(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(SalesTunnelService::class)->createSalesTunnel([
            'from_stage_id' => 999999,
            'to_stage_id' => 999998,
        ]);
    }
}
