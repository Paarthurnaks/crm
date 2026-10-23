<?php

namespace Database\Seeders;

use App\Enums\SalesStageEnum;
use App\Models\SalesStage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SalesStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SalesStage::factory([
            'title' => 'Первая стадия',
            'color' => '#333333',
            'position' => 1,
        ])->create();

        SalesStage::factory([
            'title' => 'Вторая стадия',
            'color' => '#333333',
            'position' => 2,
        ])->create();

        SalesStage::factory([
            'title' => 'Третья стадия',
            'color' => '#333333',
            'position' => 2,
        ])->create();

        SalesStage::factory([
            'title' => 'Успешно',
            'color' => '#333333',
            'position' => 2,
            'type' => SalesStageEnum::SUCCESS,
        ])->create();

        SalesStage::factory([
            'title' => 'Успешно',
            'color' => '#333333',
            'position' => 2,
            'type' => SalesStageEnum::UNSUCCESS,
        ])->create();
    }
}
