<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DealEventResource;
use App\Models\Deal;
use App\Services\DealEventService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class DealEventController extends Controller
{
    public function __construct(private readonly DealEventService $dealEventService)
    {

    }

    /**
     * @OA\Get(
     *     path="/api/deals/{dealId}/events",
     *     operationId="getDealEvents",
     *     tags={"DealEvent"},
     *     description="Метод для просмотра таймлайна сделки",
     *     summary="Метод для просмотра таймлайна сделки",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="dealId",
     *          description="id сделки",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/DealEvent"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     *
     * @param int $dealId
     * @return JsonResponse
     */
    public function getDealEvents(int $dealId): JsonResponse
    {
        $dealEvents = $this->dealEventService->getDealEvents($dealId);

        return ResponseService::success(
            DealEventResource::collection($dealEvents)
        );
    }
}
