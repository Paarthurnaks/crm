<?php

namespace Tests\Unit;

use App\Services\SalesStageService;
use InvalidArgumentException;
use Tests\TestCase;

class SalesStageColorTest extends TestCase
{
    public function test_unknown_stage_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SalesStageService)->returnStageColor(['type' => 'unknown']);
    }
}
