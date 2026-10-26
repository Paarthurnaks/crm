<?php

namespace Database\Seeders;

use App\Models\Deal;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Database\Seeder;

class DealSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(DealService::class);

        Deal::factory()
            ->count(20)
            ->make()
            ->each(function (Deal $draft) use ($service): void {
                $service->createDeal([
                    'title' => $draft->title,
                    'sales_funnel_id' => $draft->sales_funnel_id,
                    'sales_stage_id' => $draft->sales_stage_id,
                    'responsible_id' => $draft->responsible_id,
                    'note' => $draft->note,
                    'amount' => $draft->amount,
                    'client_id' => $draft->client,
                ], User::findOrFail($draft->creator_id));
            });
    }
}
