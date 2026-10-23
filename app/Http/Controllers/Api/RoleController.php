<?php

namespace App\Http\Controllers\Api;

use App\Enums\DefaultRolesEnum;
use App\Http\Controllers\Controller;
use App\Http\Filters\RoleFilter;
use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\RoleDetailResource;
use App\Models\Role;
use App\Services\ResponseService;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private RoleService $roleService)
    {  }

    /**
     * @OA\Get(
     *     path="/api/roles/",
     *     operationId="getRoles",
     *     tags={"Role"},
     *     description="Метод для просмотра списка ролей",
     *     summary="Метод для просмотра списка ролей",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="limit",
     *          description="Максимальное количество элементов на странице",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="search",
     *          description="поиск",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="title",
     *          description="фильтр по title",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="created_at_from",
     *          description="фильтр по дате создания (от)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="created_at_to",
     *          description="фильтр по дате создания (до)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="updated_at_from",
     *          description="фильтр по дате обновления (от)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="updated_at_to",
     *          description="фильтр по дате обновления (до)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/Role"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     *
     * @param RoleFilter $filter
     * @return JsonResponse
     */
    public function index(Request $request, RoleFilter $filter): JsonResponse
    {
        $queryParams = $request->all();
        $roles = $this->roleService->getRoles($filter, $queryParams);
        $total = $roles->toArray()["total"];

        return ResponseService::success(
            ListResource::make(RoleDetailResource::class, $roles, $total)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}",
     *     operationId="getRole",
     *     tags={"Role"},
     *     description="Метод для детального просмотра роли",
     *     summary="Метод для детального просмотра роли",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id роли",
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
     *                     ref="#/components/schemas/Role"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function show(Role $role): JsonResponse
    {
        return ResponseService::success(
            RoleDetailResource::make($role)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/roles/",
     *     operationId="createRole",
     *     tags={"Role"},
     *     description="Метод для создания новой роли",
     *     summary="Метод для создания новой роли",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *           @OA\Schema(
     *              required={"title"},
     *              @OA\Property(property="title",type="string",example="Администратор"),
     *              @OA\Property(
     *                  property="permissions",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="entity", type="string", example="deals"),
     *                      @OA\Property(property="view", type="string", example="all"),
     *                      @OA\Property(property="create", type="string", example="opened"),
     *                      @OA\Property(property="update", type="string", example="forbidden"),
     *                      @OA\Property(property="delete", type="string", example="own"),
     *                      @OA\Property(property="import", type="string", example="forbidden"),
     *                      @OA\Property(property="export", type="string", example="all"),
     *                  ),
     *              ),
     *              @OA\Property(
     *                  property="available_disk_entities",
     *                  type="array",
     *                  @OA\Items(type="int64", example="12"),
     *              ),
     *              @OA\Property(
     *                  property="available_view_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="9"),
     *              ),
     *              @OA\Property(
     *                  property="available_view_user_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="17"),
     *              ),
     *              @OA\Property(
     *                  property="available_create_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="9"),
     *              ),
     *              @OA\Property(
     *                  property="available_create_user_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="17"),
     *              ),
     *              @OA\Property(
     *                  property="available_update_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="9"),
     *              ),
     *              @OA\Property(
     *                  property="available_update_user_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="17"),
     *              )
     *           )
     *        ),
     *     ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/Role"
     *                 ),
     *              )
     *          )
     *      ),
     * )
     *
     * @param RoleStoreRequest $request
     * @return JsonResponse
     */
    public function store(RoleStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $role = $this->roleService->createRole($validated);

        return ResponseService::created(
            RoleDetailResource::make($role)
        );
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     operationId="updateRole",
     *     tags={"Role"},
     *     description="Метод для обновления роли",
     *     summary="Метод для обновления роли",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id роли",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *           @OA\Schema(
     *              @OA\Property(property="title",type="string",example="Администратор"),
     *              @OA\Property(
     *                  property="permissions",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="id", type="int64", example=1),
     *                      @OA\Property(property="view", type="string", example="all"),
     *                      @OA\Property(property="create", type="string", example="opened"),
     *                      @OA\Property(property="update", type="string", example="forbidden"),
     *                      @OA\Property(property="delete", type="string", example="own"),
     *                      @OA\Property(property="import", type="string", example="forbidden"),
     *                      @OA\Property(property="export", type="string", example="all"),
     *                  ),
     *              ),
     *              @OA\Property(
     *                  property="available_disk_entities",
     *                  type="array",
     *                  @OA\Items(type="int64", example="12"),
     *              ),
     *              @OA\Property(
     *                  property="available_view_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="9"),
     *              ),
     *              @OA\Property(
     *                  property="available_view_user_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="17"),
     *              ),
     *              @OA\Property(
     *                  property="available_create_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="9"),
     *              ),
     *              @OA\Property(
     *                  property="available_create_user_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="17"),
     *              ),
     *              @OA\Property(
     *                  property="available_update_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="9"),
     *              ),
     *              @OA\Property(
     *                  property="available_update_user_fields",
     *                  type="array",
     *                  @OA\Items(type="int64", example="17"),
     *              )
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
     *                     ref="#/components/schemas/Role"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     *
     * @param Role $role
     * @param RoleUpdateRequest $request
     * @return JsonResponse
     */
    public function update(Role $role, RoleUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $role = $this->roleService->updateRole($role, $validated);

        return ResponseService::success(
            RoleDetailResource::make($role)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     operationId="deleteRole",
     *     tags={"Role"},
     *     description="Метод для удаления роли",
     *     summary="Метод для удаления роли",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id роли",
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
     * @param Role $role
     * @return JsonResponse
     */
    public function destroy(Role $role): JsonResponse
    {
        foreach (DefaultRolesEnum::cases() as $enumRole) {
            if($role->original_title === $enumRole->value)  {
                return ResponseService::unSuccess(message: "Вы не можете удалить эту роль");
            }
        }

        $this->roleService->deleteRole($role);

        return ResponseService::success(message: "Роль успешно удалена");
    }
}
