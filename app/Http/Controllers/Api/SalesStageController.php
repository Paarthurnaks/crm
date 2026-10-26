<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\DealFilter;
use App\Http\Filters\SalesStageFilter;
use App\Http\Requests\SalesStageStoreRequest;
use App\Http\Requests\SalesStageUpdateRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\SalesStageResource;
use App\Models\SalesStage;
use App\Services\ResponseService;
use App\Services\SalesStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesStageController extends Controller
{
    public function __construct(private SalesStageService $salesStageService) {}

    public function index(Request $request, SalesStageFilter $filter): JsonResponse
    {
        $queryParams = $request->all();
        $salesStages = $this->salesStageService->getSalesStages($filter, $queryParams);
        $total = $salesStages->toArray()['total'];

        return ResponseService::success(
            ListResource::make(SalesStageResource::class, $salesStages, $total)
        );
    }

    public function show(SalesStage $salesStage, DealFilter $filter): JsonResponse
    {
        $this->salesStageService->setStageMetrics($salesStage, $filter);

        return ResponseService::success(
            SalesStageResource::make($salesStage)
        );
    }

    public function store(SalesStageStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $stage = $this->salesStageService->createSalesStage($validated, $validated['sales_funnel_id']);

        return ResponseService::success(
            SalesStageResource::make($stage)
        );
    }

    public function update(SalesStage $salesStage, SalesStageUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $stage = $this->salesStageService->updateSalesStage($salesStage, $validated);

        return ResponseService::success(
            SalesStageResource::make($stage)
        );
    }

    public function destroy(SalesStage $salesStage): JsonResponse
    {
        $this->salesStageService->deleteSalesStage($salesStage);

        return ResponseService::success(message: 'Стадия успешно удалена');
    }
}
