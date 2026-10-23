<?php

namespace App\Services;

use App\Enums\SalesStageEnum;
use App\Http\Filters\DealFilter;
use App\Http\Filters\SalesStageFilter;
use App\Models\SalesStage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;

class SalesStageService
{
    /**
     * @param SalesStageFilter $filter
     * @param array $queryParams
     * @return Collection
     */
    public function getSalesStages(SalesStageFilter $filter, array $queryParams): LengthAwarePaginator
    {   
        $limit = $queryParams["limit"] ?? config("constants.paginator.limit");
        return SalesStage::filter($filter)->paginate($limit);
    }

    /**
     * @param SalesStage $salesStage
     * @return void
     */
    public function setStageMetrics(SalesStage $stage, DealFilter $dealFilter): void
    {   
        $deals = $stage->deals()->filter($dealFilter)->get();
        $stage->metrics =  [
            "count" => $deals->count(),
            "total_amount" => $deals->sum("amount")
        ];

        
    }

    /**
     * @param array
     * @return SalesStage
     */
    public function createSalesStage(array $stageData, int $salesFunnelId): SalesStage
    {   
        $stage = SalesStage::create([
            "title" => $stageData["title"],
            "color" => $this->returnStageColor($stageData),
            "position" => $stageData["position"] ?? 1,
            "type" => $stageData["type"],
            "sales_funnel_id" => $salesFunnelId
        ]);

        return $stage;
    }

    /**
     * @param SalesStage $deal
     * @param array
     * @return SalesStage
     */
    public function updateSalesStage(SalesStage $stage, array $stageData): SalesStage
    {   
        if (array_key_exists("type", $stageData)) $stageData["color"] = $this->returnStageColor($stageData);

        $stage->update($stageData);
        return $stage;
    }

    /**
     * @param SalesStage $deal
     * @return void
     */
    public function deleteSalesStage(SalesStage $stage): void
    {
        $stage->delete();
    }

    /**
     * @param array $stageData
     * @return string
     */
    public function returnStageColor(array $stageData): string
    {
        switch ($stageData["type"]) {
            case (SalesStageEnum::DEFAULT->value):
                return $stageData["color"] ?? config("constants.stages.default_color");
                break;
            case (SalesStageEnum::SUCCESS->value):
                return config("constants.stages.success_color");
                break;
            case (SalesStageEnum::UNSUCCESS->value):
                return config("constants.stages.unsuccess_color");
                break;
        }   
    }
}
