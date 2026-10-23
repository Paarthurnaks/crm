<?php

namespace App\Http\Controllers\Api;

use App\Enums\DefaultRolesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SendLinkChangePasswordRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\MeResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {

    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     operationId="login",
     *     tags={"Auth"},
     *     description="Метод для авторизации пользователя",
     *     summary="Метод для авторизации пользователя",
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *           @OA\Schema(
     *              @OA\Property(property="email",type="string",example="example@242_test.com"),
     *              @OA\Property(property="password",type="string",example="242_test"),
     *           ),
     *       ),
     *     ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="token",
     *                     type="string",
     *                             example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9yJpc3MiOiJodHRwOi8vbG9jYWxob3N0L2FwaS9sb2dpbiIsImlhdCI6MTcwNjUxMDU4MCwiZXhwIjoxNzA2NTE0MTgwLCJuYmYiOjE3MDY1MTA1ODAsImp0aSI6IlpHb3ZxekZLd3ppa3VoVjciLCJzdWIiOiIyIiwicHJ2IjoiMjNiZDVjODk0OWY2MDBhZGIzOWU3MDFjNDAwODcyZGI3YTU5NzZmNyJ9.YJ3frVsQSqnOg6fIZ7nwwE6AogmXfwEA-me-g9wDvBg"
     *                 ),
     *                 @OA\Property(
     *                     property="expires_in",
     *                     type="int64",
     *                     example=3600
     *                 )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="401",
     *          description="Unauthorized",
     *      )
     * )
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $credentials = [
            "email" => $validated["email"],
            "password" => $validated["password"]
        ];

        $token = Auth::attempt($credentials);
        if (!$token) return ResponseService::unauthorized();

        $user = Auth::user();

        $user->update(["active" => true, "last_login" => now()]);

        return ResponseService::success(
            AuthResource::make(UserResource::make($user), $token)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     operationId="logout",
     *     tags={"Auth"},
     *     description="Метод для выхода из системы",
     *     summary="Метод для выхода из системы",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *          response="200",
     *          description="OK",
     *      ),
     *      @OA\Response(
     *          response="401",
     *          description="Unauthorized",
     *      )
     * )
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        Auth::user()->update(["active" => false]);
        Auth::logout();

        return ResponseService::success();
    }

    /**
     * @OA\Post(
     *     path="/api/refresh",
     *     operationId="refresh",
     *     tags={"Auth"},
     *     description="Метод для обновления jwt токенов пользователя",
     *     summary="Метод для обновления jwt токенов пользователя",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="user",
     *                     ref="#/components/schemas/User"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="401",
     *          description="Unauthorized",
     *      )
     * )
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        $user = Auth::user();
        $token = Auth::refresh();

        return ResponseService::success(
            AuthResource::make(UserResource::make($user), $token)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     operationId="me",
     *     tags={"Auth"},
     *     description="Метод для получения информации о текущем авторизованном пользователе/проверки авторизации",
     *     summary="Метод для получения информации о текущем авторизованном пользователе/проверки авторизации",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="user",
     *                     ref="#/components/schemas/User"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="401",
     *          description="Unauthorized",
     *      )
     * )
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();

        return ResponseService::success(
            MeResource::make($user)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/change-password/send-link",
     *     operationId="sendLinkChangePassword",
     *     tags={"Auth"},
     *     description="Метод для отправки письма со ссылкой на смену пароля",
     *     summary="Метод для отправки письма со ссылкой на смену пароля",
     *
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *           @OA\Schema(
     *              @OA\Property(property="email",type="string",example="example@242_test.com"),
     *           ),
     *       ),
     *     ),
     *     @OA\Response(response="200",
     *          description="Ссылка на смену пароля отправлена на почту",
     *      ),
     *      @OA\Response(response="404",
     *          description="Пользователь с таким email не существует",
     *      )
     * )
     *
     * @param SendLinkChangePasswordRequest $request
     * @return JsonResponse
     */
    public function sendLinkChangePassword(SendLinkChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authService->sendLinkChangePassword($validated["email"]);

        return ResponseService::success(message: "Ссылка на смену пароля отправлена на почту");
    }

    /**
     * @OA\Post(
     *     path="/api/change-password/change",
     *     operationId="changePassword",
     *     tags={"Auth"},
     *     description="Метод для смены пароля",
     *     summary="Метод для смены пароля",
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *           @OA\Schema(
     *              @OA\Property(property="email",type="string",example="example@242_test.com"),
     *              @OA\Property(property="code",type="string",example="JIG4uMkrwX5FfcK6vIBFq29wvLojKobszO1QHXcjcMlSVw6HIduGR7mlDMB6nkAB"),
     *              @OA\Property(property="password",type="string",example="newPassword"),
     *           ),
     *       ),
     *     ),
     *     @OA\Response(response="200",
     *          description="Пароль успешно обновлён!",
     *      ),
     *      @OA\Response(response="404",
     *          description="Пользователь с таким email не существует",
     *      ),
     *      @OA\Response(response="400",
     *          description="Неправильный код подтверждения"),
     *      ),
     * @OA\Response(response="400",
     *          description="Время кода подтверждения истекло"),
     *      ),
     * )
     *
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->changePassword($request->validated());

            return ResponseService::success(message: "Пароль успешно обновлён!");
        } catch (NotFoundHttpException $e) {
            return ResponseService::notFound(message: "Пользователь с таким email не существует");
        } catch (BadRequestHttpException $e) {
            return ResponseService::badRequest(message: "Неправильный код подтверждения");
        } catch (TokenExpiredException $e) {
            return ResponseService::badRequest(message: "Время кода подтверждения истекло");
        }
    }


    /**
     * @OA\Post(
     *     path="/api/change-user-avatar",
     *     operationId="changeAvatar",
     *     tags={"Auth"},
     *     description="Метод для смены пароля",
     *     summary="Метод для смены пароля",
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="multipart/form-data",
     *           @OA\Schema(
     *              @OA\Property(property="avatar", type="binary"),
     *           ),
     *       ),
     *     ),
     *     @OA\Response(response="200",
     *          description="Аватарка успешно изменена!",
     *      ),
     * )
     *
     * @return JsonResponse
     */

    public function changeAvatar(Request $request): JsonResponse
    {
        if ($request->has('avatar')) {
            Auth::user()->clearMediaCollection();
            Auth::user()->addMedia($request->avatar)->toMediaCollection();
        }

        return ResponseService::success(message: "Аватар успешно изменен");
    }
}
