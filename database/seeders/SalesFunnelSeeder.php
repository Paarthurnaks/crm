<?php

namespace Database\Seeders;

use App\Enums\SalesStageEnum;
use App\Models\Deal;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use Illuminate\Database\Seeder;

class SalesFunnelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mockData = ['Оформление счёта', 'Банкротство', 'Урегулирование финансовых обязательств', 'Создание и реализация плана по выплате долгов', 'Слияние и поглощение компаний в рамках процедуры банкротства', 'Обеспечение финансовой поддержки в период реструктуризации', 'Разрешение споров между кредиторами и должником'];
        $salesFunnelTitles = ['Воронка ЛГБ', 'Работа с партнёрами', 'Реализация', 'Реализация с имуществом', 'Реструктуризация'];

        for ($i = 0; $i < count($salesFunnelTitles); $i++) {
            $salesFunnel = SalesFunnel::factory([
                'title' => $salesFunnelTitles[$i],
                'position' => $i + 1,
            ])->create();

            $arrayStagesTitles = ['Размещение', 'Публикации размещены', 'Запросы в гос. органы', 'Ответы из гос. органов'];
            $arrayStagesColors = ['#39a8ef', '#2fc6f6', '#55d0e0', '#47e4c2'];

            for ($j = 0; $j < count($arrayStagesTitles); $j++) {
                if ($arrayStagesColors[$j] == '#7bd500') {
                    $type = SalesStageEnum::SUCCESS->value;
                } elseif ($arrayStagesColors[$j] == '#ff5752') {
                    $type = SalesStageEnum::UNSUCCESS->value;
                } else {
                    $type = SalesStageEnum::DEFAULT->value;
                }
                $salesStage = SalesStage::factory([
                    'title' => $arrayStagesTitles[$j],
                    'color' => $arrayStagesColors[$j],
                    'position' => $j + 1,
                    'type' => $type,
                    'sales_funnel_id' => $salesFunnel->id,
                ])->create();

                Deal::factory([
                    'title' => $arrayStagesTitles[array_rand($arrayStagesTitles)],
                    'sales_funnel_id' => $salesFunnel->id,
                    'sales_stage_id' => $salesStage->id,
                ])->count(1)->create();
            }
        }
    }
}
