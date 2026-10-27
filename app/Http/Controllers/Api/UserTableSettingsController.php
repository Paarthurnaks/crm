<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserTableSettingsRequest;
use App\Http\Resources\UserTableSettingsResource;
use App\Services\ResponseService;
use App\Services\UserTableSettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserTableSettingsController extends Controller
{
    public function __construct(private UserTableSettingsService $userTableSettingsService) {}

    /**
     * @OA\Get(
     *     path="/api/user-table-settings",
     *     operationId="getUserTableSettings",
     *     tags={"UserTableSettings"},
     *     description="Метод для вывода пользователю его настроек таблиц",
     *     summary="Метод для вывода пользователю его настроек таблиц",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="entity",
     *          description="Фильтр по entity",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/UserTableSettings"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $queryParams = $request->all();
        $tableSettings = $this->userTableSettingsService->getUserTableSettings($queryParams, $request->user());

        if (is_null($tableSettings)) {
            return ResponseService::success();
        }

        $responseData = $tableSettings instanceof Collection ? UserTableSettingsResource::collection($tableSettings) : UserTableSettingsResource::make($tableSettings);

        return ResponseService::success(
            $responseData
        );
    }

    /**
     * @OA\Put(
     *     path="/api/user-table-settings",
     *     operationId="updateUserTableSettings",
     *     tags={"UserTableSettings"},
     *     description="Метод для обновления настроек таблицы пользователей",
     *     summary="Метод для обновления настроек таблицы пользователей",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="entity",type="string",example="deals"),
     *              @OA\Property(
     *                  property="settings",
     *                  type="object",
     *                  @OA\Property(
     *                      property="on_page",
     *                      example=20
     *                  )
     *              ),
     *           ),
     *       ),
     *     ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/UserTableSettings"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     */
    public function update(UpdateUserTableSettingsRequest $request): JsonResponse
    {
        $tableSettings = $this->userTableSettingsService->updateUserTableSettings($request->validated(), $request->user());

        return ResponseService::success(
            UserTableSettingsResource::make($tableSettings)
        );
    }
}
