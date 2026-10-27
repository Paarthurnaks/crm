<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\DealFilter;
use App\Http\Filters\SalesFunnelFilter;
use App\Http\Requests\SalesFunnelStoreRequest;
use App\Http\Requests\SalesFunnelUpdateRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\SalesFunnelResource;
use App\Models\SalesFunnel;
use App\Services\ResponseService;
use App\Services\SalesFunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SalesFunnelController extends Controller
{
    public function __construct(private readonly SalesFunnelService $salesFunnelService) {}

    public function index(Request $request, SalesFunnelFilter $salesFunnelFilter, DealFilter $dealFilter): JsonResponse
    {
        $queryParams = $request->all();
        $salesFunnels = $this->salesFunnelService->getSalesFunnels($salesFunnelFilter, $dealFilter, $queryParams, $request->user());
        $total = $salesFunnels->toArray()['total'];

        return ResponseService::success(
            ListResource::make(SalesFunnelResource::class, $salesFunnels, $total)
        );
    }

    public function show(Request $request, SalesFunnel $salesFunnel, DealFilter $dealFilter): JsonResponse
    {
        $this->salesFunnelService->getMetricsFromAllStages($salesFunnel, $dealFilter, $request->user());

        return ResponseService::success(
            SalesFunnelResource::make($salesFunnel)
        );
    }

    public function store(SalesFunnelStoreRequest $request): JsonResponse
    {

        try {
            $validated = $request->validated();
            $salesFunnel = $this->salesFunnelService->createSalesFunnel($validated, $request->user());

            return ResponseService::created(
                SalesFunnelResource::make($salesFunnel)
            );
        } catch (AccessDeniedHttpException) {
            return ResponseService::forbidden(message: 'Создавать направления может только администратор');
        } catch (BadRequestHttpException) {
            return ResponseService::badRequest(message: 'Должны быть обязательно 1 обычная, 1 успешная и 1 неуспешная стадия');
        }
    }

    public function update(SalesFunnel $salesFunnel, SalesFunnelUpdateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $salesFunnel = $this->salesFunnelService->updateSalesFunnel($salesFunnel, $validated);

            return ResponseService::success(
                SalesFunnelResource::make($salesFunnel)
            );
        } catch (AccessDeniedHttpException $e) {
            return ResponseService::forbidden(message: $e->getMessage());
        } catch (BadRequestHttpException $e) {
            return ResponseService::badRequest(message: $e->getMessage());
        }
    }

    public function destroy(Request $request, SalesFunnel $salesFunnel): JsonResponse
    {
        try {
            $this->salesFunnelService->deleteSalesFunnel($salesFunnel, $request->user());

            return ResponseService::success(message: 'Направление успешно удалено');
        } catch (AccessDeniedHttpException $e) {
            return ResponseService::forbidden($e->getMessage());
        }
    }
}
