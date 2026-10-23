<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesTunnelStoreRequest;
use App\Http\Resources\SalesTunnelResource;
use App\Models\SalesTunnel;
use App\Services\ResponseService;
use App\Services\SalesTunnelService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SalesTunnelController extends Controller
{
    public function __construct(private SalesTunnelService $salesTunnelService)
    {  }

    /**
     * @OA\Post(
     *     path="/api/sales-tunnels/",
     *     operationId="createSalesTunnel",
     *     tags={"SalesTunnel"},
     *     description="Метод для создания нового тунеля",
     *     summary="Метод для создания нового тунеля",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *           @OA\Schema(
     *              @OA\Property(property="from_stage_id", type="int64", example=4),
     *              @OA\Property(property="to_stage_id", type="int64", example=7),
     *           ),
     *       ),
     *     ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/SalesTunnel"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     *
     * @param SalesTunnelStoreUpdateRequest $request
     * @return JsonResponse
     */
    public function store(SalesTunnelStoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $salesTunnel = $this->salesTunnelService->createSalesTunnel($validated);

            return ResponseService::created(
                SalesTunnelResource::make($salesTunnel)
            );
        } catch (AccessDeniedHttpException $e) {
            return ResponseService::forbidden(message: $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/sales-tunnels/{id}",
     *     operationId="deleteSalesTunnel",
     *     tags={"SalesTunnel"},
     *     description="Метод для удаления тунеля",
     *     summary="Метод для удаления тунеля",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id тунеля",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Response(response="200",
     *          description="OK",
     *     ),
     *     @OA\Response(response="404",
     *          description="NOT FOUND"
     *     ),
     *     @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     *
     * @param SalesTunnel $salesTunnel
     * @return JsonResponse
     */
    public function destroy(SalesTunnel $salesTunnel): JsonResponse
    {
        $this->salesTunnelService->deleteSalesTunnel($salesTunnel);

        return ResponseService::success(message: "Тунель успешно удален");
    }
}
