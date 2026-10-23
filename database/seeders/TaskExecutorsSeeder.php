<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskExecutorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tasks = Task::all();

        $tasks->each(function (Task $task) {
            $users = User::inRandomOrder()->limit(rand(1, 3))->get();
            $task->executors()->sync($users->pluck('id'));
        });
    }
}
