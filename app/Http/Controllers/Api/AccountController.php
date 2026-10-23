<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\AccountFilter;
use App\Http\Requests\Account\StoreRequest;
use App\Http\Requests\Account\UpdateRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\ListResource;
use App\Models\Account;
use App\Services\AccountService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accountService) {}

    /**
     * @OA\Get(
     *     path="/api/accounts",
     *     operationId="getAccounts",
     *     tags={"Account"},
     *     description="Получение списка выставленных счетов",
     *     summary="Получение списка выставленных счетов",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="limit",
     *          description="Количество элементов на странице",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *              default=50,
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="author_id",
     *          description="id того, кто выставил счет",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="deal_id",
     *          description="id привязанной сделки",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="invoice_date_from",
     *          description="фильтр по дате выставления (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="invoice_date_to",
     *          description="фильтр по дате выставления (до)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="created_at_from",
     *          description="фильтр по дате создания (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="created_at_to",
     *          description="фильтр по дате создания (до)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="updated_at_from",
     *          description="фильтр по дате обновления (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="updated_at_to",
     *          description="фильтр по дате обновления (до)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
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
     *                     type="array",
     *
     *                     @OA\Items(
     *                        ref="#/components/schemas/Account"
     *                     )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function index(Request $request, AccountFilter $filter): JsonResponse
    {
        $queryParams = $request->all();
        $accounts = $this->accountService->getList($filter, $queryParams);
        $total = $accounts->toArray()['total'];

        return ResponseService::success(
            ListResource::make(AccountResource::class, $accounts, $total)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/accounts/{id}",
     *     operationId="getAccount",
     *     tags={"Account"},
     *     description="Метод для получения конкретного выставленного счета",
     *     summary="Метод для получения конкретного выставленного счета",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id счета",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *              example=19
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
     *                     ref="#/components/schemas/Account"
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
    public function show(int $id): JsonResponse
    {
        $account = Account::find($id);

        if (! $account) {
            return ResponseService::notFound(
                message: __('Счет не найден')
            );
        }

        return ResponseService::success(
            AccountResource::make($account)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/accounts",
     *     operationId="createAccount",
     *     tags={"Account"},
     *     description="Метод для выставления счета",
     *     summary="Метод для выставления счета",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="sum", type="double", example=70000),
     *              @OA\Property(property="deal_id", type="int", example=14),
     *              @OA\Property(property="author_id", type="int", example=98),
     *              @OA\Property(property="invoice_date", type="string", example="2023-02-01"),
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
     *                     ref="#/components/schemas/Account"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $account = $this->accountService->create($validated);

            return ResponseService::created(
                AccountResource::make($account)
            );
        } catch (Exception $e) {
            return ResponseService::badRequest(
                $e->getMessage()
            );
        }
    }

    /**
     * @OA\Put(
     *     path="/api/accounts/{id}",
     *     operationId="updateAccount",
     *     tags={"Account"},
     *     description="Метод для обновления выставленного счета",
     *     summary="Метод для обновления выставленного счета",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id счета",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="sum", type="double", example=70000),
     *              @OA\Property(property="deal_id", type="int", example=14),
     *              @OA\Property(property="author_id", type="int", example=98),
     *              @OA\Property(property="invoice_date", type="string", example="2023-02-01"),
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
     *                     ref="#/components/schemas/Account"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $account = Account::find($id);

        if (! $account) {
            return ResponseService::notFound(
                message: __('Счет не найден')
            );
        }

        $validated = $request->validated();

        $account = $this->accountService->update($account, $validated);

        return ResponseService::success(
            AccountResource::make($account)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/accounts/{id}",
     *     operationId="deleteAccount",
     *     tags={"Account"},
     *     description="Метод для удаления выставленного счета",
     *     summary="Метод для удаления выставленного счета",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id счета",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
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
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $account = Account::find($id);

            if (! $account) {
                return ResponseService::notFound(message: __('Счет не найден'));
            }

            $this->accountService->delete($account);

            return ResponseService::success(message: __('Счет удален'));
        } catch (\Throwable $e) {
            return ResponseService::badRequest($e->getMessage());
        }
    }
}
