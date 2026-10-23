<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory
 */
class TaskFactory extends Factory
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
            'title' => fake()->title,
            "description" => fake()->text,
            "creator_id" => $user->id,
            "deal_id" => $deal->id,
            "deadline" => fake()->date('Y-m-d H:i:s'),
        ];
    }

    public function withExecutors(int $count = 1): TaskFactory
    {
        return $this->afterCreating(function (Task $task) use ($count) {
            $users = User::inRandomOrder()->limit($count)->get();
            $task->executors()->attach($users);
        });
    }
}
