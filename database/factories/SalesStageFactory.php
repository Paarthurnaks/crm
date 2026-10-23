<?php

namespace Database\Factories;

use App\Enums\SalesStageEnum;
use App\Models\SalesFunnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesStage>
 */
class SalesStageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->title(),
            'color' => '#000000',
            'position' => rand(0, 10),
            'type' => SalesStageEnum::DEFAULT->value,
            'sales_funnel_id' => SalesFunnel::COMMON,
        ];
    }
}
