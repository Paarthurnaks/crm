<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::first();
        $deal = Deal::first();

        return [
            "sum" => fake()->randomFloat(2, 0, 100000),
            "author_id" => $user->id,
            "deal_id" => $deal->id,
            "invoice_date" => fake()->date('Y-m-d'),
        ];
    }
}
