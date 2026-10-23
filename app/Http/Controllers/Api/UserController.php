<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\UserFilter;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ResponseService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    /**
     * @OA\Get(
     *     path="/api/users/",
     *     operationId="getUsers",
     *     tags={"User"},
     *     description="Метод для просмотра списка пользователей",
     *     summary="Метод для просмотра списка пользователей",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="limit",
     *          description="Максимальное количество элементов на странице",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="search",
     *          description="поиск",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *           name="linked_partner_id",
     *           description="поиск связанных с партнером по id клиентов",
     *           required=false,
     *           in="query",
     *
     *           @OA\Schema(
     *               type="int64"
     *           )
     *       ),
     *
     *     @OA\Parameter(
     *           name="only",
     *           description="поиск по конкретной роли",
     *           required=false,
     *           in="query",
     *
     *           @OA\Schema(
     *               type="string",
     *               enum={"partners", "without_clients", "without_personals", "clients"},
     *               description="Доступные значения: partners (Только партнеры), without_clients (Без клиентов), without_personals (Без клиентов и партнеров), clients(только клиенты)"
     *           )
     *       ),
     *
     *     @OA\Parameter(
     *          name="name",
     *          description="фильтр по name",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="surname",
     *          description="фильтр по surname",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="email",
     *          description="фильтр по email",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="role",
     *          description="фильтр по role_id",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="division",
     *          description="фильтр по division_id",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="active",
     *          description="фильтр по active",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="last_login_from",
     *          description="фильтр по last_login (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="last_login_to",
     *          description="фильтр по last_login (до)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
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
     *                     ref="#/components/schemas/User"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function index(Request $request, UserFilter $filter): JsonResponse
    {
        $queryParams = $request->all();

        $users = $this->userService->getUsers($filter, $queryParams);

        $total = $users->toArray()['total'];

        return ResponseService::success(
            ListResource::make(UserResource::class, $users, $total)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     operationId="getUser",
     *     tags={"User"},
     *     description="Метод для детального просмотра пользователя",
     *     summary="Метод для детального просмотра пользователя",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id пользователя",
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
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/User"
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
    public function show(User $user): JsonResponse
    {
        return ResponseService::success(
            UserResource::make($user)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/users/",
     *     operationId="createUser",
     *     tags={"User"},
     *     description="Метод для создания нового пользователя",
     *     summary="Метод для создания нового пользователя",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="multipart/form-data",
     *
     *           @OA\Schema(
     *              required={"name", "surname", "email", "password"},
     *
     *              @OA\Property(property="name",type="string",example="Ivan"),
     *              @OA\Property(property="surname",type="string",example="Ivanov"),
     *              @OA\Property(property="post",type="string",example="Бухгалтер"),
     *              @OA\Property(property="email", type="string", example="ivanivanov@test.com"),
     *              @OA\Property(property="password", type="string", example="testpassword"),
     *              @OA\Property(property="role_id", type="int64", example=1),
     *              @OA\Property(property="division_id", type="int64", example=1),
     *              @OA\Property(property="avatar", type="binary"),
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
     *                     ref="#/components/schemas/User"
     *                 ),
     *              )
     *          )
     *      ),
     * )
     */
    public function store(UserStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->userService->createUser($validated);

        return ResponseService::created(
            UserResource::make($user)
        );
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     operationId="updateUser",
     *     tags={"User"},
     *     description="Метод для обновления пользователя",
     *     summary="Метод для обновления пользователя",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id пользователя",
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
     *           mediaType="multipart/form-data",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="name",type="string",example="Ivan"),
     *              @OA\Property(property="surname",type="string",example="Ivanov"),
     *              @OA\Property(property="post",type="string",example="Бухгалтер"),
     *              @OA\Property(property="email", type="string", example="ivanivanov@242_test.com"),
     *              @OA\Property(property="role_id", type="int64", example=1),
     *              @OA\Property(property="division_id", type="int64", example=1),
     *              @OA\Property(property="avatar", type="binary"),
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
     *                     ref="#/components/schemas/User"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     *
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function update(User $user, UserUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->userService->updateUser($user, $validated);

        return ResponseService::success(
            UserResource::make($user)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     operationId="deleteUser",
     *     tags={"User"},
     *     description="Метод для удаления пользователя",
     *     summary="Метод для удаления пользователя",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id пользователя",
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
    public function destroy(User $user): JsonResponse
    {
        $this->userService->deleteUser($user);

        return ResponseService::success(message: 'Пользователь успешно удалён');
    }
}
