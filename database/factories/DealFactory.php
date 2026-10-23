<?php

namespace Database\Factories;

use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory
 */
class DealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salesFunnel = SalesFunnel::first();
        $salesStage = SalesStage::first();
        $user = User::first();
        return [
            "title" => fake()->sentence(),
            "sales_funnel_id" => $salesFunnel->id,
            "sales_stage_id" => $salesStage->id,
            "creator_id" => $user->id,
            "responsible_id" => $user->id,
            "note" => fake()->text(),
            "amount" => fake()->randomFloat(2, 1000, 100000),
            "client" => fake()->sentence()
        ];
    }
}
